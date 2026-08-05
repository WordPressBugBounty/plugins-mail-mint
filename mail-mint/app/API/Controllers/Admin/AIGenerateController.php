<?php
/**
 * REST API AI Generate Controller
 *
 * Point-of-use, single-shot AI helpers — distinct from the conversational
 * copilot in AIChatController. One request, one model round-trip, plain text
 * back:
 *
 *   POST /mrm/v1/ai/generate  — { task, ...task params } → { task, text }
 *
 * Supported tasks:
 *   subject_line                 — write one campaign subject line
 *   preview_text                 — write one campaign preview text (preheader)
 *   compose_email                — edit the email open in the builder, or write a new one
 *   rewrite_text                 — rewrite one text block (enhance, spelling, custom) in place
 *   summarize_contact            — summarize a contact profile (cached, per-locale)
 *   summarize_campaign_analytics — summarize one campaign (get-campaign-analytics)
 *   summarize_site_analytics     — summarize site KPIs (get-site-analytics; cached, per-filter+locale)
 *   extract_business_info        — pull business profile fields from a website (setup wizard)
 *
 * Analytics summary tasks pull their data through the shared MCP tool surface
 * (ToolGateway), so each tool's own permission_callback still gates access.
 * The contact and site-analytics summaries are handled separately (see
 * summarize_contact() and summarize_site_analytics()): each caches its result
 * (per contact, or per filter+locale) and only calls the model on an explicit
 * generate/regenerate, so opening the panel is instant and free by default.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

defined( 'ABSPATH' ) || exit;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\API\Controllers\BaseController;
use Mint\MRM\Internal\AI\Context\ContactSummaryContext;
use Mint\MRM\Internal\AI\Settings\AISettings;
use Mint\MRM\Internal\AI\SingleShotAIService;
use Mint\MRM\Internal\AI\EmailTemplateEditor;
use Mint\MRM\Internal\AI\ToolGateway;
use Mint\MRM\Internal\MCP\Helpers\EmailComposer;
use MRM\Common\MrmCommon;
use WP_REST_Request;

class AIGenerateController extends BaseController {

    use Singleton;

    /**
     * Default permission check (route-level callbacks gate each endpoint).
     *
     * @return bool
     */
    public function rest_permissions_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * System prompts per task. Deliberately terse and output-only so the
     * result drops straight into a field or panel with no post-processing.
     */
    private const SYSTEMS = array(
        'subject_line'                 => 'You are an expert email marketing copywriter. Write ONE high-performing subject line for the campaign described below. Keep it under 60 characters, specific, and curiosity- or benefit-driven. Avoid spammy words and ALL CAPS. Return ONLY the subject line text — no quotes, no labels, no explanation.',
        'preview_text'                 => 'You are an expert email marketing copywriter. Write ONE preview text (preheader) for the campaign described below. Keep it under 100 characters and make it complement — not repeat — the subject line. Return ONLY the preview text — no quotes, no labels, no explanation.',
        // summarize_contact uses a locale-aware prompt built in contact_summary_system_prompt().

        'summarize_campaign_analytics' => 'You are an email marketing analyst. From the campaign analytics JSON below, write a plain-language performance summary in markdown: lead with the headline result, then 2–4 bullets on opens, clicks, unsubscribes and bounces and what they imply, then one final line starting with "Recommendation:". Reference the actual numbers and never invent data.',
        'summarize_site_analytics'     => 'You are an email marketing analyst. From the site analytics JSON below, write a plain-language summary in markdown of how email marketing is performing this period: lead with the headline, then 2–4 bullets on contact growth, sends and engagement versus the previous period, then one final line starting with "Recommendation:". Reference the actual numbers and never invent data.',
    );

    /**
     * System prompt for extract_business_info. Kept separate from SYSTEMS
     * because the task returns strict JSON, not free-form text.
     */
    private const BUSINESS_INFO_SYSTEM = 'You are extracting business profile details from a single company website\'s homepage for a CRM business-profile form. Only use information explicitly present in the supplied signals — NEVER invent or guess a phone number, address, or name that is not present. If a field is not present in the signals, return an empty string for it. Prefer structured data (JSON-LD / meta tags) over the visible text when both are given. Write country and state as full names, not abbreviations, when you can tell. For logo_url, choose the single best match from the "Candidate logo URLs" list if one clearly represents the company logo, otherwise return an empty string — never output a URL that is not in that list. Return ONLY a single minified JSON object with EXACTLY these keys and string values, no markdown code fences, no commentary: {"business_name":"","phone":"","address_line_1":"","address_line_2":"","city":"","state":"","postal":"","country":"","logo_url":""}';

    /**
     * Whitelisted keys for the extract_business_info JSON payload, in output order.
     */
    private const BUSINESS_INFO_KEYS = array( 'business_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postal', 'country', 'logo_url' );

    /**
     * POST /mrm/v1/ai/generate
     *
     * @param WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function generate( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $task   = sanitize_key( $params['task'] ?? '' );

        // The contact summary has its own cache-aware, richer-context flow.
        if ( 'summarize_contact' === $task ) {
            return $this->summarize_contact( $params );
        }

        // The site-analytics summary is likewise cached (per filter+locale) so
        // opening the Analytics tab never re-bills the model on its own.
        if ( 'summarize_site_analytics' === $task ) {
            return $this->summarize_site_analytics( $params );
        }

        // Website → business profile extraction has its own fetch + JSON flow.
        if ( 'extract_business_info' === $task ) {
            return $this->extract_business_info( $params );
        }

        // Whole-email composition for the email builder's AI panel.
        if ( 'compose_email' === $task ) {
            return $this->compose_email( $params );
        }

        // In-place rewrite of a single text block from the rich-text toolbar.
        if ( 'rewrite_text' === $task ) {
            return $this->rewrite_text( $params );
        }

        // Subject line / preview text power the inline AI panel: several
        // suggestions per request plus quick "modify" actions on existing text.
        if ( 'subject_line' === $task || 'preview_text' === $task ) {
            return $this->generate_writer_suggestions( $task, $params );
        }

        if ( ! isset( self::SYSTEMS[ $task ] ) ) {
            return $this->get_error_response( __( 'Unsupported or missing task.', 'mrm' ), 400 );
        }

        $hint      = trim( (string) ( $params['prompt'] ?? '' ) );
        $user_text = $this->build_user_text( $task, $params, $hint );
        if ( is_wp_error( $user_text ) ) {
            return $this->get_error_response( $user_text->get_error_message(), 400 );
        }

        $result = SingleShotAIService::generate( self::SYSTEMS[ $task ], $user_text );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task' => $task,
                'text' => $result,
            )
        );
    }

    // -----------------------------------------------------------------------
    // Subject line / preview text — multi-suggestion writer + modify actions
    // -----------------------------------------------------------------------

    /**
     * Generate several subject-line or preview-text options in one request.
     *
     * Powers the inline AI panel on the campaign editor. Beyond a fresh
     * "generate", it supports quick modify actions on the text already in the
     * field — shorten, simplify, rephrase — and a free-form "custom"
     * instruction (used for "change tone to…", "translate to…", or anything the
     * user types). Returns an array of distinct options for the user to pick.
     *
     * @param string $task   'subject_line' or 'preview_text'.
     * @param array  $params Request params (action, current_text, instruction/prompt, count, campaign context).
     * @return \WP_REST_Response
     */
    private function generate_writer_suggestions( string $task, array $params ) {
        $allowed_actions = array( 'generate', 'shorten', 'simplify', 'rephrase', 'custom' );
        $action          = sanitize_key( $params['action'] ?? 'generate' );
        if ( ! in_array( $action, $allowed_actions, true ) ) {
            $action = 'generate';
        }

        $current_text = trim( (string) ( $params['current_text'] ?? '' ) );
        // "instruction" is the panel's field; fall back to "prompt" for the
        // legacy single-shot callers that used that key.
        $instruction = trim( (string) ( $params['instruction'] ?? ( $params['prompt'] ?? '' ) ) );
        $count       = (int) ( $params['count'] ?? 3 );
        $count       = max( 1, min( 5, $count ) );
        $context     = $this->build_writer_context( $params );

        // Modify actions need existing text to work on.
        if ( in_array( $action, array( 'shorten', 'simplify', 'rephrase' ), true ) && '' === $current_text ) {
            return $this->get_error_response( __( 'Add some text to the field first, then let AI refine it.', 'mrm' ), 400 );
        }
        // A custom instruction must actually say something.
        if ( 'custom' === $action && '' === $instruction ) {
            return $this->get_error_response( __( 'Type an instruction for the AI to follow.', 'mrm' ), 400 );
        }
        // A fresh generation needs at least some grounding.
        if ( 'generate' === $action && '' === $current_text && '' === $instruction && '' === $context ) {
            return $this->get_error_response( __( 'Describe the campaign or give an instruction first.', 'mrm' ), 400 );
        }

        $system    = $this->build_writer_system( $task, $action, $count );
        $user_text = $this->build_writer_user_text( $task, $current_text, $instruction, $context );

        $result = SingleShotAIService::generate( $system, $user_text );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $suggestions = $this->parse_suggestions( $result, $count );
        if ( empty( $suggestions ) ) {
            return $this->get_error_response( __( 'The AI returned no usable suggestions. Please try again.', 'mrm' ), 422 );
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task'        => $task,
                'action'      => $action,
                'suggestions' => $suggestions,
                // Keep "text" for backward-compatible single-shot callers.
                'text'        => $suggestions[0],
            )
        );
    }

    /**
     * Collect the campaign context lines shared by every writer action.
     *
     * @param array $params Request params.
     * @return string Newline-joined "Label: value" lines (may be empty).
     */
    private function build_writer_context( array $params ) {
        $details = array();
        // 'subject_line' lets the preview-text writer complement the subject.
        foreach ( array( 'campaign_title', 'subject_line', 'goal', 'audience', 'tone' ) as $key ) {
            $val = trim( (string) ( $params[ $key ] ?? '' ) );
            if ( '' !== $val ) {
                $details[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $val;
            }
        }
        return implode( "\n", $details );
    }

    /**
     * Build the system prompt for a writer action. Deliberately terse and
     * output-only: the model must return a JSON array of plain strings so the
     * panel can render each option with no post-processing beyond parsing.
     *
     * @param string $task   'subject_line' or 'preview_text'.
     * @param string $action One of generate|shorten|simplify|rephrase|custom.
     * @param int    $count  Number of options to produce.
     * @return string
     */
    private function build_writer_system( string $task, string $action, int $count ) {
        $kind  = 'preview_text' === $task ? 'email preview text (also called the preheader)' : 'email subject line';
        $limit = 'preview_text' === $task ? 'under 100 characters' : 'under 60 characters';

        switch ( $action ) {
            case 'shorten':
                $directive = 'Rewrite the provided text so each option is noticeably shorter and punchier while keeping its core message.';
                break;
            case 'simplify':
                $directive = 'Rewrite the provided text using simpler, clearer language that is easy to scan and understand.';
                break;
            case 'rephrase':
                $directive = 'Rephrase the provided text with fresh wording while keeping the same intent and roughly the same length.';
                break;
            case 'custom':
                $directive = 'Rewrite the provided text — or write from the campaign context when no text is provided — to satisfy the user instruction given below.';
                break;
            case 'generate':
            default:
                $directive = 'Write compelling new options based on the campaign context and any instruction given below.';
                break;
        }

        return sprintf(
            'You are an expert email marketing copywriter helping craft an %1$s. %2$s '
            . 'Produce exactly %3$d distinct options. Each option must be %4$s, specific, and free of spammy words or ALL CAPS. '
            . 'Mail Mint personalization/merge tags use double curly braces with no spaces, e.g. {{contact.first_name}}, with an optional fallback after a pipe like {{contact.first_name|friend}}. '
            . 'Preserve any such tags in the provided text exactly, and if you add personalization use ONLY this {{...}} format (never square brackets, never spaces inside the braces) — never translate or alter the text inside the braces. '
            . 'Return ONLY a minified JSON array of plain strings, for example ["First option","Second option"]. No numbering, no keys, no markdown, no commentary.',
            $kind,
            $directive,
            $count,
            $limit
        );
    }

    /**
     * Build the user-message body for a writer action: the current text (if
     * any), the campaign context, and the user's instruction.
     *
     * @param string $task         'subject_line' or 'preview_text'.
     * @param string $current_text Text already in the field, if any.
     * @param string $instruction  Free-form user instruction, if any.
     * @param string $context      Campaign context lines.
     * @return string
     */
    private function build_writer_user_text( string $task, string $current_text, string $instruction, string $context ) {
        $label = 'preview_text' === $task ? 'preview text' : 'subject line';
        $parts = array();
        if ( '' !== $current_text ) {
            $parts[] = sprintf( "Current %s:\n\"%s\"", $label, $current_text );
        }
        if ( '' !== $context ) {
            $parts[] = "Campaign context:\n" . $context;
        }
        if ( '' !== $instruction ) {
            $parts[] = 'User instruction: ' . $instruction;
        }
        if ( empty( $parts ) ) {
            $parts[] = sprintf( 'Write a compelling %s for a marketing email.', $label );
        }
        return implode( "\n\n", $parts );
    }

    /**
     * Parse the model's response into a clean list of suggestion strings.
     *
     * The model is asked for a JSON array of strings, but tolerates a code
     * fence and falls back to line-splitting when the model ignores the format.
     * String helpers are used over regex per project convention.
     *
     * @param string $raw   Raw model output.
     * @param int    $count Maximum suggestions to return.
     * @return string[]
     */
    private function parse_suggestions( string $raw, int $count ) {
        $raw = trim( $raw );

        // Tolerate a markdown code fence around the JSON.
        if ( 0 === strpos( $raw, '```' ) ) {
            $raw = trim( trim( $raw, '`' ) );
            if ( 0 === stripos( $raw, 'json' ) ) {
                $raw = trim( substr( $raw, 4 ) );
            }
        }

        $items   = array();
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $entry ) {
                if ( is_string( $entry ) ) {
                    $items[] = $entry;
                } elseif ( is_array( $entry ) && isset( $entry['text'] ) ) {
                    $items[] = (string) $entry['text'];
                }
            }
        }

        // Fallback: the model returned lines instead of a JSON array.
        if ( empty( $items ) ) {
            $normalized = str_replace( array( "\r\n", "\r" ), "\n", $raw );
            foreach ( explode( "\n", $normalized ) as $line ) {
                // Strip common list markers and surrounding whitespace.
                $line = ltrim( trim( $line ), "-*0123456789.) \t" );
                if ( '' !== $line ) {
                    $items[] = $line;
                }
            }
        }

        $clean = array();
        foreach ( $items as $item ) {
            $item = trim( wp_strip_all_tags( (string) $item ) );
            // Drop a stray pair of surrounding quotes the model may add.
            $item = trim( $item, "\"'" );
            if ( '' === $item ) {
                continue;
            }
            $clean[] = mb_substr( $item, 0, 190 );
            if ( count( $clean ) >= $count ) {
                break;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    // -----------------------------------------------------------------------
    // rewrite_text — in-place rewrite of one text block (rich-text toolbar)
    // -----------------------------------------------------------------------

    /**
     * Rewrite a single text block's content and hand back HTML the block can
     * swap in immediately.
     *
     * Backs the "AI" button on the email builder's rich-text toolbar. It never
     * touches the rest of the email — the caller sends only the text of the
     * block (or the current selection within it) and drops the result straight
     * back where it came from. Supported actions: enhance, spelling, shorten,
     * expand, and a free-form custom instruction.
     *
     * The input may carry the small inline-HTML whitelist Mail Mint text blocks
     * allow, and merge tags like {{contact.first_name}}; both must survive
     * untouched, so the model is told to preserve them and the result is run
     * through the same wp_kses whitelist rather than stripped to plain text.
     *
     * @param array $params Request params (action, text, instruction).
     * @return \WP_REST_Response
     */
    private function rewrite_text( array $params ) {
        $allowed = array( 'enhance', 'spelling', 'shorten', 'expand', 'custom' );
        $action  = sanitize_key( $params['action'] ?? 'enhance' );
        $is_email_html = 'email_html' === sanitize_key( $params['format'] ?? '' );
        if ( ! in_array( $action, $allowed, true ) ) {
            $action = 'enhance';
        }

        // Text may legitimately contain inline HTML; keep the whitelist, don't
        // flatten it with sanitize_text_field.
        $text        = trim( wp_kses( (string) ( $params['text'] ?? '' ), $this->rewrite_allowed_html( $is_email_html ) ) );
        $instruction = trim( (string) ( $params['instruction'] ?? ( $params['prompt'] ?? '' ) ) );

        if ( '' === $text ) {
            return $this->get_error_response( __( 'Select some text for the AI to work on first.', 'mrm' ), 400 );
        }
        if ( 'custom' === $action && '' === $instruction ) {
            return $this->get_error_response( __( 'Type an instruction for the AI to follow.', 'mrm' ), 400 );
        }

        $result = SingleShotAIService::generate(
            $this->build_rewrite_system( $action, $is_email_html ),
            $this->build_rewrite_user_text( $text, $instruction )
        );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $clean = $this->clean_rewrite_result( $result, $is_email_html );
        if ( '' === $clean ) {
            return $this->get_error_response( __( 'The AI returned nothing usable. Please try again.', 'mrm' ), 422 );
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task'   => 'rewrite_text',
                'action' => $action,
                'text'   => $clean,
            )
        );
    }

    /**
     * The inline-HTML whitelist a rewritten text block may contain — the same
     * small set Mail Mint's email text blocks allow.
     *
     * @return array
     */
    private function rewrite_allowed_html( bool $is_email_html = false ) {
        $allowed = array(
            'a'      => array(
                'href'   => true,
                'target' => true,
            ),
            'strong' => array(),
            'b'      => array(),
            'em'     => array(),
            'i'      => array(),
            'u'      => array(),
            'span'   => array( 'style' => true ),
            'br'     => array(),
        );

        if ( ! $is_email_html ) {
            return $allowed;
        }

        return array_merge(
            $allowed,
            array(
                'p'          => array( 'style' => true, 'align' => true ),
                'div'        => array( 'style' => true, 'align' => true ),
                'h1'         => array( 'style' => true, 'align' => true ),
                'h2'         => array( 'style' => true, 'align' => true ),
                'h3'         => array( 'style' => true, 'align' => true ),
                'h4'         => array( 'style' => true, 'align' => true ),
                'h5'         => array( 'style' => true, 'align' => true ),
                'h6'         => array( 'style' => true, 'align' => true ),
                'ul'         => array( 'style' => true ),
                'ol'         => array( 'style' => true ),
                'li'         => array( 'style' => true ),
                'blockquote' => array( 'style' => true ),
                'hr'         => array( 'style' => true ),
                'table'      => array( 'style' => true, 'width' => true, 'height' => true, 'align' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true ),
                'thead'      => array(),
                'tbody'      => array(),
                'tr'         => array( 'style' => true, 'align' => true ),
                'td'         => array( 'style' => true, 'align' => true, 'valign' => true, 'colspan' => true, 'rowspan' => true, 'width' => true, 'height' => true ),
                'th'         => array( 'style' => true, 'align' => true, 'valign' => true, 'colspan' => true, 'rowspan' => true, 'width' => true, 'height' => true ),
                'img'        => array( 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true ),
            )
        );
    }

    /**
     * System prompt for a rewrite action.
     *
     * @param string $action One of enhance|spelling|shorten|expand|custom.
     * @return string
     */
    private function build_rewrite_system( string $action, bool $is_email_html = false ) {
        switch ( $action ) {
            case 'spelling':
                $directive = 'Fix ONLY spelling, grammar and punctuation. Do not rephrase, restructure, or change the meaning, tone, or length.';
                break;
            case 'shorten':
                $directive = 'Make the text noticeably shorter and tighter while keeping its meaning and tone.';
                break;
            case 'expand':
                $directive = 'Expand the text with a little more detail and persuasion while keeping its meaning and tone.';
                break;
            case 'custom':
                $directive = 'Rewrite the text to satisfy the user instruction given below.';
                break;
            case 'enhance':
            default:
                $directive = 'Improve clarity, flow and impact for a marketing email while keeping the original meaning, language and roughly the same length.';
                break;
        }

        $prompt  = 'You are an expert email marketing copy editor. ' . $directive . ' ';
        $prompt .= 'Return ONLY the rewritten text, with no quotes, labels, explanation, or markdown code fence. ';
        $prompt .= $is_email_html
            ? 'Return valid email HTML only. Preserve the existing HTML structure and styles where they make sense; use only standard email tags such as paragraphs, headings, lists, links, spans, tables, images and line breaks. '
            : 'Preserve the existing inline HTML tags (<strong>, <em>, <b>, <i>, <u>, <a>, <span>, <br>) where they make sense, and do not introduce any other HTML. ';
        $prompt .= 'Keep every merge tag exactly as written — tokens in double curly braces such as {{contact.first_name}} or {{contact.first_name|there}} must never be translated, reworded, or altered. ';
        $prompt .= 'Match the language of the input text.';

        /**
         * Filter the AI text-rewrite system prompt.
         *
         * @since 1.20.0
         *
         * @param string $prompt The system prompt.
         * @param string $action The rewrite action.
         */
        return apply_filters( 'mint_mail/ai/rewrite_text_system_prompt', $prompt, $action );
    }

    /**
     * User message for a rewrite action.
     *
     * @param string $text        The block text (may contain inline HTML/merge tags).
     * @param string $instruction Free-form instruction (custom action).
     * @return string
     */
    private function build_rewrite_user_text( string $text, string $instruction ) {
        $parts = array();
        if ( '' !== $instruction ) {
            $parts[] = 'Instruction: ' . $instruction;
        }
        $parts[] = "Text:\n" . $text;
        return implode( "\n\n", $parts );
    }

    /**
     * Strip a stray code fence / surrounding quotes the model may add, then run
     * the result through the inline-HTML whitelist.
     *
     * @param string $raw Raw model output.
     * @return string
     */
    private function clean_rewrite_result( string $raw, bool $is_email_html = false ) {
        $raw = trim( $raw );
        if ( preg_match( '/```(?:html)?\s*(.*?)\s*```/is', $raw, $match ) ) {
            $raw = trim( $match[1] );
        }
        // Drop a single pair of wrapping quotes, but never touch inner ones.
        if ( strlen( $raw ) >= 2 ) {
            $first = $raw[0];
            $last  = $raw[ strlen( $raw ) - 1 ];
            if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
                $raw = trim( substr( $raw, 1, -1 ) );
            }
        }
        return trim( wp_kses( $raw, $this->rewrite_allowed_html( $is_email_html ) ) );
    }

    // -----------------------------------------------------------------------
    // compose_email — a whole email template for the drag-and-drop builder
    // -----------------------------------------------------------------------

    /**
     * Edit the email currently open in the builder — or write a new one.
     *
     * Which of the two happens is decided by the model, not by guessing at the
     * user's wording. When a `template` is supplied, its editable blocks are
     * listed for the model, and "change the CTA label" comes back as a single
     * operation against one block id: {@see EmailTemplateEditor} applies it to
     * that node and every other node stays byte-identical. Only an explicit
     * request for a new email (or an empty canvas) produces a full rewrite,
     * where the model returns the constrained content structure that
     * {@see EmailComposer} turns into a fresh node tree.
     *
     * Nothing is persisted either way — the panel drops the result into the
     * open editor and the user still saves.
     *
     * @param array $params Request params (prompt, template, style_preset, campaign context).
     * @return \WP_REST_Response
     */
    private function compose_email( array $params ) {
        $prompt = trim( (string) ( $params['prompt'] ?? '' ) );
        if ( '' === $prompt ) {
            return $this->get_error_response( __( 'Describe the email you want the AI to write.', 'mrm' ), 400 );
        }

        $template = isset( $params['template'] ) && is_array( $params['template'] ) ? $params['template'] : array();
        $outline  = ! empty( $template ) ? EmailTemplateEditor::outline( $template ) : array();

        $system = empty( $outline )
            ? $this->build_compose_email_system()
            : $this->build_edit_email_system();

        $result = SingleShotAIService::generate( $system, $this->build_compose_email_user_text( $prompt, $params, $outline ) );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $decoded = $this->decode_model_json( $result );
        if ( is_wp_error( $decoded ) ) {
            return $this->get_error_response( $decoded->get_error_message(), 422 );
        }

        // Targeted edit — the common case once an email exists.
        if ( ! empty( $outline ) && ! empty( $decoded['ops'] ) && is_array( $decoded['ops'] ) ) {
            return $this->apply_email_edit( $template, $decoded );
        }

        return $this->rewrite_email( $decoded );
    }

    /**
     * Apply an edit plan to the open template and return the patched tree.
     *
     * @param array $template Current builder node tree.
     * @param array $decoded  Decoded model response ({ ops, summary, subject, preview_text }).
     * @return \WP_REST_Response
     */
    private function apply_email_edit( array $template, array $decoded ) {
        $edit = EmailTemplateEditor::applyOps( $template, $decoded['ops'] );

        if ( 0 === $edit['applied'] ) {
            $reason = ! empty( $edit['skipped'] ) ? ' ' . reset( $edit['skipped'] ) : '';
            return $this->get_error_response(
                __( 'The AI could not apply that change to this email. Try describing the block you want changed.', 'mrm' ) . $reason,
                422
            );
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task'         => 'compose_email',
                'mode'         => 'edit',
                'summary'      => sanitize_text_field( (string) ( $decoded['summary'] ?? '' ) ),
                'applied'      => $edit['applied'],
                'skipped'      => $edit['skipped'],
                // Only sent when the model was asked to change them, so the
                // panel leaves the user's own subject alone otherwise.
                'subject'      => sanitize_text_field( (string) ( $decoded['subject'] ?? '' ) ),
                'preview_text' => sanitize_text_field( (string) ( $decoded['preview_text'] ?? '' ) ),
                'template'     => $edit['template'],
            )
        );
    }

    /**
     * Compose a brand-new email from the model's content structure.
     *
     * @param array $decoded Decoded model response ({ subject, preview_text, style, content }).
     * @return \WP_REST_Response
     */
    private function rewrite_email( array $decoded ) {
        if ( empty( $decoded['content'] ) || ! is_array( $decoded['content'] ) ) {
            return $this->get_error_response( __( 'The AI returned an email we could not read. Please try again.', 'mrm' ), 422 );
        }

        $style    = isset( $decoded['style'] ) && is_array( $decoded['style'] ) ? $decoded['style'] : array();
        $composed = EmailComposer::compose( $decoded['content'], $style );
        if ( is_wp_error( $composed ) ) {
            return $this->get_error_response( $composed->get_error_message(), 422 );
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task'         => 'compose_email',
                'mode'         => 'rewrite',
                'summary'      => sanitize_text_field( (string) ( $decoded['summary'] ?? '' ) ),
                'subject'      => sanitize_text_field( (string) ( $decoded['subject'] ?? '' ) ),
                'preview_text' => sanitize_text_field( (string) ( $decoded['preview_text'] ?? '' ) ),
                'style'        => $style,
                'template'     => $composed['json'],
                'html'         => $composed['html'],
            )
        );
    }

    /**
     * System prompt for compose_email: the content contract the model must
     * emit, derived from EmailComposer's own capabilities so the two can never
     * drift apart.
     *
     * @return string
     */
    private function build_compose_email_system() {
        $prompt  = 'You are an expert email marketing designer and copywriter working inside Mail Mint. ';
        $prompt .= 'Write a complete, ready-to-send marketing email and return it as a SINGLE minified JSON object — no markdown, no code fence, no commentary. ';
        $prompt .= 'Shape: {"subject":"","preview_text":"","summary":"","style":{"preset":""},"content":{"hero":{"heading":"","subheading":""},"sections":[],"footer_text":"","include_footer":true}}. ';
        $prompt .= 'style.preset must be one of: ' . implode( ', ', EmailComposer::capabilities()['style_presets'] ) . '. ';
        $prompt .= $this->email_section_rules();
        $prompt .= 'Sections render in the order given — open with a hero, then alternate copy and at least one button, and keep the whole email to 4-10 sections. ';
        $prompt .= 'Keep the subject under 60 characters and the preview text under 100. "summary" is one short sentence for the user describing what you wrote. ';
        $prompt .= $this->email_writing_rules();

        /**
         * Filter the AI email-composition system prompt.
         *
         * @since 1.20.0
         *
         * @param string $prompt The system prompt.
         */
        return apply_filters( 'mint_mail/ai/compose_email_system_prompt', $prompt );
    }

    /**
     * System prompt for editing the email already open in the builder.
     *
     * The whole point of this path is restraint: the user asked to change one
     * thing, so the model returns operations for that one thing and everything
     * else in their email survives untouched. A full rewrite stays available,
     * but only when the user actually asks for a new email.
     *
     * @return string
     */
    private function build_edit_email_system() {
        $prompt  = 'You are an expert email marketing designer working inside the Mail Mint email builder. ';
        $prompt .= 'The user has an email OPEN in the editor. Its editable blocks are listed below, each with an "id". ';
        $prompt .= 'Return a SINGLE minified JSON object — no markdown, no code fence, no commentary. ';
        $prompt .= 'DEFAULT BEHAVIOUR — make the smallest edit that satisfies the instruction: ';
        $prompt .= '{"mode":"edit","summary":"","ops":[]}. ';
        $prompt .= 'Every op targets ONE block by its id: ';
        $prompt .= '{"op":"update_text","id":"","text":""} rewrites a text block; ';
        $prompt .= '{"op":"update_button","id":"","label":"","url":""} changes a button (send only the keys you are changing); ';
        $prompt .= '{"op":"update_image","id":"","src":"","alt":""} changes an image or a hero background; ';
        $prompt .= '{"op":"replace","id":"","section":{}} swaps a block for a different one; ';
        $prompt .= '{"op":"delete","id":""} removes a block; ';
        $prompt .= '{"op":"insert_before","id":"","section":{}} and {"op":"insert_after","id":"","section":{}} add a new block next to an existing one. ';
        $prompt .= 'The "section" object of replace/insert ops uses the section schema below. ';
        $prompt .= 'CRITICAL: emit ops ONLY for blocks the instruction actually asks you to change. Never "improve" or re-word blocks that were not mentioned. ';
        $prompt .= 'If the instruction names a section ("the CTA", "the second paragraph", "the headline"), match it against the block list and touch that block alone. ';
        $prompt .= 'Do not include the subject or preview_text keys unless the user asked you to change them. ';
        $prompt .= '"summary" is one short sentence telling the user what you changed. ';
        $prompt .= 'ONLY when the user explicitly asks for a brand-new email, a full rewrite, or a different email altogether, respond instead with ';
        $prompt .= '{"mode":"rewrite","subject":"","preview_text":"","summary":"","style":{"preset":"' . implode( '|', EmailComposer::capabilities()['style_presets'] ) . '"},"content":{"hero":{"heading":"","subheading":""},"sections":[],"footer_text":"","include_footer":true}} — this discards their current email, so never choose it for a targeted change. ';
        $prompt .= 'A request like "write a welcome email" IS a request for a new email; a request naming any part of the email ("the CTA", "the intro", "add a paragraph after X") is NOT. ';
        $prompt .= 'If the listed blocks are clearly an untouched starter template full of placeholder copy and the user asks for real content, rewrite. ';
        $prompt .= $this->email_section_rules();
        $prompt .= $this->email_writing_rules();

        /**
         * Filter the AI email-editing system prompt.
         *
         * @since 1.20.0
         *
         * @param string $prompt The system prompt.
         */
        return apply_filters( 'mint_mail/ai/edit_email_system_prompt', $prompt );
    }

    /**
     * The section schema shared by the compose and edit prompts.
     *
     * @return string
     */
    private function email_section_rules() {
        $rules  = 'A section object has a "type" from: ' . implode( ', ', EmailComposer::capabilities()['section_types'] ) . '. ';
        $rules .= 'heading and paragraph need "text"; bullets needs "items" (array of strings); button needs "label" and an absolute "url"; ';
        $rules .= 'image needs "src" (absolute URL) and optional "alt"; spacer takes "height" in pixels; divider takes nothing. ';
        return $rules;
    }

    /**
     * The content rules shared by the compose and edit prompts.
     *
     * @return string
     */
    private function email_writing_rules() {
        $rules  = 'NEVER invent an image URL or a link: only use URLs supplied in the context, or already present in the email. ';
        $rules .= 'If you have no destination for a button or an <a href>, link it to the site URL given in the context — never example.com, yourdomain.com, yoursite.com or any other stand-in domain, and never a relative path. ';
        $rules .= 'Personalization uses double curly braces with no spaces, e.g. {{contact.first_name}} with an optional fallback like {{contact.first_name|there}} — never square brackets, and never alter the text inside existing braces. ';
        $rules .= 'Text may contain only these inline tags: <strong>, <em>, <b>, <i>, <u>, <br>, <a href="">. No other HTML, no unsubscribe or preference links (Mail Mint appends a compliant footer itself). ';
        $rules .= 'Write in the WordPress site language for locale ' . $this->contact_summary_locale() . ', unless the user asks for another language.';
        return $rules;
    }

    /**
     * Build the user message for compose_email: the instruction, whatever
     * campaign/business context the caller supplied, and the blocks of the
     * email currently open in the builder.
     *
     * @param string $prompt  The user's instruction.
     * @param array  $params  Request params.
     * @param array  $outline Editable blocks of the open email, if any.
     * @return string
     */
    private function build_compose_email_user_text( string $prompt, array $params, array $outline ) {
        $parts = array();

        $context = array();
        foreach ( array( 'campaign_title', 'subject_line', 'goal', 'audience', 'tone' ) as $key ) {
            $val = trim( (string) ( $params[ $key ] ?? '' ) );
            if ( '' !== $val ) {
                $context[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $val;
            }
        }

        $business = MrmCommon::get_business_name();
        if ( $business ) {
            $context[] = 'Business name: ' . $business;
        }
        $context[] = 'Site URL: ' . home_url( '/' );

        $preset = sanitize_key( $params['style_preset'] ?? '' );
        if ( in_array( $preset, EmailComposer::capabilities()['style_presets'], true ) ) {
            $context[] = 'Requested style preset: ' . $preset;
        }

        $logo = isset( $params['logo_url'] ) ? esc_url_raw( (string) $params['logo_url'] ) : '';
        if ( '' !== $logo ) {
            $context[] = 'Logo URL (safe to use): ' . $logo;
        }

        $parts[] = "Context:\n" . implode( "\n", $context );

        if ( ! empty( $outline ) ) {
            $parts[] = "Blocks of the email currently open in the editor, in order — edit these by id:\n" . wp_json_encode( $outline );
        }

        $parts[] = 'Instruction: ' . $prompt;

        return implode( "\n\n", $parts );
    }

    /**
     * Decode the model's JSON envelope, tolerating a markdown code fence. What
     * the object must contain depends on which branch it took, so shape
     * validation belongs to the caller.
     *
     * @param string $raw Raw model output.
     * @return array|\WP_Error
     */
    private function decode_model_json( string $raw ) {
        $raw = trim( $raw );
        if ( preg_match( '/```(?:json)?\s*(.*?)\s*```/is', $raw, $match ) ) {
            $raw = $match[1];
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return new \WP_Error( 'ai_bad_json', __( 'The AI returned an email we could not read. Please try again.', 'mrm' ) );
        }

        return $decoded;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the user-message content for a task, pulling live data through the
     * shared MCP tool surface for summary tasks.
     *
     * @param string $task   Task key.
     * @param array  $params Request params.
     * @param string $hint   Optional free-text user instruction.
     * @return string|\WP_Error
     */
    private function build_user_text( string $task, array $params, string $hint ) {
        switch ( $task ) {
            case 'subject_line':
            case 'preview_text':
                $details = array();
                foreach ( array( 'campaign_title', 'goal', 'audience', 'tone' ) as $key ) {
                    $val = trim( (string) ( $params[ $key ] ?? '' ) );
                    if ( '' !== $val ) {
                        $details[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $val;
                    }
                }
                if ( '' !== $hint ) {
                    $details[] = 'Instruction: ' . $hint;
                }
                if ( empty( $details ) ) {
                    return new \WP_Error( 'ai_no_context', __( 'Describe the campaign or give an instruction first.', 'mrm' ) );
                }
                return implode( "\n", $details );

            case 'summarize_campaign_analytics':
                $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
                if ( ! $campaign_id ) {
                    return new \WP_Error( 'ai_no_context', __( 'A campaign_id is required.', 'mrm' ) );
                }
                return $this->fetch_context(
                    'mail-mint/get-campaign-analytics',
                    array(
                        'campaign_id'      => $campaign_id,
                        'include_timeline' => false,
                    ),
                    $hint
                );
        }

        return new \WP_Error( 'ai_no_context', __( 'Unsupported task.', 'mrm' ) );
    }

    /**
     * Execute a read tool via the shared gateway and wrap its JSON as the
     * user-message body. The tool's own permission_callback enforces access.
     *
     * @param string $tool Ability name.
     * @param array  $args Ability arguments.
     * @param string $hint Optional free-text user instruction.
     * @return string|\WP_Error
     */
    private function fetch_context( string $tool, array $args, string $hint ) {
        $res = ToolGateway::executeTool( $tool, $args );
        if ( $res['is_error'] ) {
            return new \WP_Error( 'ai_context_error', __( 'Could not load the data to summarize.', 'mrm' ) );
        }
        $body = $res['content'];
        if ( '' !== $hint ) {
            $body .= "\n\nUser focus: " . $hint;
        }
        return $body;
    }

    // -----------------------------------------------------------------------
    // Business info extraction — fetch a website, extract signals, ask the model
    // -----------------------------------------------------------------------

    /**
     * Fetch a business's homepage and ask the model to extract profile fields
     * from it, for the setup wizard's "Import from website" flow.
     *
     * @param array $params Request params (website_url).
     * @return \WP_REST_Response
     */
    private function extract_business_info( array $params ) {
        $url = isset( $params['website_url'] ) ? esc_url_raw( trim( (string) $params['website_url'] ), array( 'http', 'https' ) ) : '';
        if ( '' === $url ) {
            return $this->get_error_response( __( 'Enter a valid website URL.', 'mrm' ), 400 );
        }

        $html = $this->fetch_website_html( $url );
        if ( is_wp_error( $html ) ) {
            return $this->get_error_response( $html->get_error_message(), 422 );
        }

        $signals = $this->extract_page_signals( $html, $url );

        $result = SingleShotAIService::generate( self::BUSINESS_INFO_SYSTEM, $this->build_business_extraction_prompt( $url, $signals ) );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $business = $this->parse_business_json( $result );
        if ( is_wp_error( $business ) ) {
            return $this->get_error_response( $business->get_error_message(), 422 );
        }

        // Never trust a logo URL the model didn't copy verbatim from a real
        // candidate found on the page — fall back to the first candidate instead.
        if ( '' === $business['logo_url'] || ! in_array( $business['logo_url'], $signals['logo_candidates'], true ) ) {
            $business['logo_url'] = $signals['logo_candidates'][0] ?? '';
        }

        return $this->get_success_response(
            '',
            200,
            array(
                'task'     => 'extract_business_info',
                'business' => $business,
            )
        );
    }

    /**
     * Fetch a URL's HTML body, guarding against SSRF via wp_safe_remote_get's
     * built-in private/loopback host rejection.
     *
     * @param string $url URL to fetch.
     * @return string|\WP_Error
     */
    private function fetch_website_html( string $url ) {
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => 12,
                'redirection'         => 3,
                'limit_response_size' => 1500000,
                'user-agent'          => 'MailMint-Onboarding/1.0 (+https://getmailmint.com)',
            )
        );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ai_fetch_failed', __( 'Could not reach that website. Check the URL and try again.', 'mrm' ) );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            return new \WP_Error( 'ai_fetch_failed', __( 'That website could not be loaded. Check the URL and try again.', 'mrm' ) );
        }

        $html = wp_remote_retrieve_body( $response );
        if ( '' === trim( $html ) ) {
            return new \WP_Error( 'ai_fetch_empty', __( 'That website returned no content to read.', 'mrm' ) );
        }

        return $html;
    }

    /**
     * Pull deterministic signals out of a homepage's HTML: JSON-LD
     * Organization/LocalBusiness data, meta tags, favicon/og:image candidates,
     * and a truncated plain-text excerpt for the model to fall back on.
     *
     * @param string $html Raw HTML body.
     * @param string $url  Source URL (for resolving relative asset URLs).
     * @return array{ text: string, structured: array, logo_candidates: string[] }
     */
    private function extract_page_signals( string $html, string $url ) {
        $structured      = array();
        $logo_candidates = array();

        // JSON-LD Organization / LocalBusiness blocks.
        if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
            foreach ( $matches[1] as $block ) {
                $decoded = json_decode( trim( $block ), true );
                if ( ! is_array( $decoded ) ) {
                    continue;
                }
                // A JSON-LD block may be a single object or an array/@graph of them.
                $candidates = isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ? $decoded['@graph'] : array( $decoded );
                foreach ( $candidates as $entry ) {
                    if ( ! is_array( $entry ) || ! isset( $entry['@type'] ) ) {
                        continue;
                    }
                    $type = is_array( $entry['@type'] ) ? implode( ',', $entry['@type'] ) : (string) $entry['@type'];
                    if ( ! preg_match( '/Organization|LocalBusiness|Corporation|Store/i', $type ) ) {
                        continue;
                    }
                    $structured = $entry;
                    if ( ! empty( $entry['logo'] ) ) {
                        $logo_candidates[] = $this->resolve_url( $url, is_array( $entry['logo'] ) ? ( $entry['logo']['url'] ?? '' ) : $entry['logo'] );
                    }
                    break 2;
                }
            }
        }

        // <link rel="icon"> and og:image as logo fallbacks.
        if ( preg_match_all( '#<link[^>]+rel=["\'](?:shortcut icon|icon|apple-touch-icon)["\'][^>]*href=["\']([^"\']+)["\']#i', $html, $matches ) ) {
            foreach ( $matches[1] as $href ) {
                $logo_candidates[] = $this->resolve_url( $url, $href );
            }
        }
        if ( preg_match( '#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $match ) ) {
            $logo_candidates[] = $this->resolve_url( $url, $match[1] );
        }
        $logo_candidates = array_values( array_unique( array_filter( $logo_candidates ) ) );

        // og:site_name / title / description as extra text hints.
        $hints = array();
        if ( preg_match( '#<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $match ) ) {
            $hints[] = 'og:site_name: ' . html_entity_decode( $match[1], ENT_QUOTES );
        }
        if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $match ) ) {
            $hints[] = 'Page title: ' . html_entity_decode( trim( wp_strip_all_tags( $match[1] ) ), ENT_QUOTES );
        }
        if ( preg_match( '#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $match ) ) {
            $hints[] = 'Meta description: ' . html_entity_decode( $match[1], ENT_QUOTES );
        }

        // Visible body text, stripped of script/style, truncated for the prompt.
        $body = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html );
        $text = html_entity_decode( wp_strip_all_tags( (string) $body ), ENT_QUOTES );
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        $text = mb_substr( $text, 0, 6000 );

        return array(
            'text'            => implode( "\n", $hints ) . "\n\n" . $text,
            'structured'      => $structured,
            'logo_candidates' => array_slice( $logo_candidates, 0, 5 ),
        );
    }

    /**
     * Resolve a possibly-relative asset URL against the page URL it was found on.
     *
     * @param string $base_url Page URL the asset was discovered on.
     * @param mixed  $maybe_relative Asset URL/path (or non-string; returns '').
     * @return string
     */
    private function resolve_url( string $base_url, $maybe_relative ) {
        $maybe_relative = is_string( $maybe_relative ) ? trim( $maybe_relative ) : '';
        if ( '' === $maybe_relative ) {
            return '';
        }
        if ( preg_match( '#^https?://#i', $maybe_relative ) ) {
            return esc_url_raw( $maybe_relative, array( 'http', 'https' ) );
        }
        $parts  = wp_parse_url( $base_url );
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        if ( '' === $host ) {
            return '';
        }
        if ( 0 === strpos( $maybe_relative, '//' ) ) {
            return esc_url_raw( $scheme . ':' . $maybe_relative, array( 'http', 'https' ) );
        }
        if ( 0 === strpos( $maybe_relative, '/' ) ) {
            return esc_url_raw( $scheme . '://' . $host . $maybe_relative, array( 'http', 'https' ) );
        }
        $base_path = isset( $parts['path'] ) ? trim( dirname( $parts['path'] ), '/' ) : '';
        $path      = '' !== $base_path ? '/' . $base_path . '/' . $maybe_relative : '/' . $maybe_relative;
        return esc_url_raw( $scheme . '://' . $host . $path, array( 'http', 'https' ) );
    }

    /**
     * Build the user-message text for extract_business_info: structured
     * signals first (most trustworthy), then a plain-text excerpt fallback.
     *
     * @param string $url     Source URL.
     * @param array  $signals Output of extract_page_signals().
     * @return string
     */
    private function build_business_extraction_prompt( string $url, array $signals ) {
        $parts   = array();
        $parts[] = 'Website: ' . $url;
        if ( ! empty( $signals['structured'] ) ) {
            $parts[] = "Structured data found on the page (most trustworthy source):\n" . wp_json_encode( $signals['structured'] );
        }
        if ( ! empty( $signals['logo_candidates'] ) ) {
            $parts[] = "Candidate logo URLs:\n" . implode( "\n", $signals['logo_candidates'] );
        }
        $parts[] = "Visible page text (may include navigation/footer noise):\n\"\"\"\n" . $signals['text'] . "\n\"\"\"";
        return implode( "\n\n", $parts );
    }

    /**
     * Parse and sanitize the model's JSON response into the whitelisted
     * business-info key set.
     *
     * @param string $raw Model output, expected to be a JSON object.
     * @return array|\WP_Error
     */
    private function parse_business_json( string $raw ) {
        $raw = trim( $raw );
        // Tolerate a model wrapping the JSON in a markdown code fence despite instructions.
        if ( preg_match( '/```(?:json)?\s*(.*?)\s*```/is', $raw, $match ) ) {
            $raw = $match[1];
        }

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return new \WP_Error( 'ai_bad_json', __( 'Could not read business details from that website. Try entering them manually.', 'mrm' ) );
        }

        $business = array();
        foreach ( self::BUSINESS_INFO_KEYS as $key ) {
            $value             = isset( $decoded[ $key ] ) ? (string) $decoded[ $key ] : '';
            $business[ $key ] = 'logo_url' === $key ? esc_url_raw( $value, array( 'http', 'https' ) ) : sanitize_text_field( $value );
        }

        return $business;
    }

    // -----------------------------------------------------------------------
    // Site analytics summary — cache-aware, per filter + locale
    // -----------------------------------------------------------------------

    /**
     * Generate or return a cached markdown summary of site-wide analytics for
     * a date-filter window.
     *
     * Mirrors summarize_contact(): cached per filter and locale and only
     * (re)generated on an explicit generate/regenerate flag, so opening the
     * Analytics tab is instant and does not re-bill the model on every view.
     *
     *   { task, summary: { content, generated_at, provider, model, locale, filter }, cached }
     *
     * @param array $params Request params (filter, generate, regenerate, prompt).
     * @return \WP_REST_Response
     */
    private function summarize_site_analytics( array $params ) {
        $filter     = sanitize_key( $params['filter'] ?? 'last_30_days' );
        $generate   = isset( $params['generate'] ) && 'yes' === $params['generate'];
        $regenerate = isset( $params['regenerate'] ) && 'yes' === $params['regenerate'];
        $locale     = $this->contact_summary_locale();
        $cache_key  = $this->site_analytics_summary_cache_key( $filter, $locale );

        $cached = get_transient( $cache_key );
        $cached = is_array( $cached ) ? $cached : array();

        // Serve the cached summary unless the caller asked to regenerate.
        if ( ! $regenerate && $this->is_cached_summary_valid( $cached, $locale ) ) {
            return $this->get_success_response(
                '',
                200,
                array(
                    'task'    => 'summarize_site_analytics',
                    'summary' => $cached,
                    'cached'  => true,
                )
            );
        }

        // No cache and no explicit generate → return empty so the panel can
        // render its "generate" affordance without spending any tokens.
        if ( ! $generate && ! $regenerate ) {
            return $this->get_success_response(
                '',
                200,
                array(
                    'task'    => 'summarize_site_analytics',
                    'summary' => array(),
                    'cached'  => false,
                )
            );
        }

        $hint      = trim( (string) ( $params['prompt'] ?? '' ) );
        $user_text = $this->fetch_context(
            'mail-mint/get-site-analytics',
            array(
                'filter'                 => $filter,
                'include_deliverability' => true,
            ),
            $hint
        );
        if ( is_wp_error( $user_text ) ) {
            return $this->get_error_response( $user_text->get_error_message(), 400 );
        }

        $result = SingleShotAIService::generate( self::SYSTEMS['summarize_site_analytics'], $user_text );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $provider = AISettings::getActiveProvider();
        $summary  = array(
            'content'      => $result,
            'generated_at' => gmdate( 'c' ),
            'provider'     => $provider,
            'model'        => '' !== $provider ? AISettings::getModel( $provider ) : '',
            'locale'       => $locale,
            'filter'       => $filter,
        );

        // Analytics windows shift as new sends/opens land, so unlike the
        // contact summary's indefinite cache this one self-refreshes daily.
        set_transient( $cache_key, $summary, DAY_IN_SECONDS );

        return $this->get_success_response(
            '',
            200,
            array(
                'task'    => 'summarize_site_analytics',
                'summary' => $summary,
                'cached'  => false,
            )
        );
    }

    /**
     * Transient key for a site-analytics summary, namespaced by filter and locale.
     *
     * @param string $filter Analytics date-range filter key.
     * @param string $locale Site locale.
     * @return string
     */
    private function site_analytics_summary_cache_key( $filter, $locale ) {
        return 'mint_ai_asum_' . md5( $filter . '_' . $locale );
    }

    // -----------------------------------------------------------------------
    // Contact summary — cache-aware, richer context, per-locale
    // -----------------------------------------------------------------------

    /**
     * Generate or return a cached markdown summary for a contact profile.
     *
     * Mirrors the FluentCRM workflow: the summary is cached per contact and
     * locale and only (re)generated on an explicit generate/regenerate flag, so
     * opening a profile is instant and does not re-bill the model.
     *
     *   { task, summary: { content, generated_at, provider, model, locale, counts }, cached }
     *
     * @param array $params Request params (contact_id, generate, regenerate, prompt).
     * @return \WP_REST_Response
     */
    private function summarize_contact( array $params ) {
        $contact_id = (int) ( $params['contact_id'] ?? 0 );
        if ( ! $contact_id ) {
            return $this->get_error_response( __( 'A contact_id is required.', 'mrm' ), 400 );
        }

        $generate   = isset( $params['generate'] ) && 'yes' === $params['generate'];
        $regenerate = isset( $params['regenerate'] ) && 'yes' === $params['regenerate'];
        $locale     = $this->contact_summary_locale();
        $cache_key  = $this->contact_summary_cache_key( $contact_id, $locale );

        $cached = get_transient( $cache_key );
        $cached = is_array( $cached ) ? $cached : array();

        // Serve the cached summary unless the caller asked to regenerate.
        if ( ! $regenerate && $this->is_cached_summary_valid( $cached, $locale ) ) {
            return $this->get_success_response(
                '',
                200,
                array(
                    'task'    => 'summarize_contact',
                    'summary' => $cached,
                    'cached'  => true,
                )
            );
        }

        // No cache and no explicit generate → return empty so the panel can
        // render its "generate" affordance without spending any tokens.
        if ( ! $generate && ! $regenerate ) {
            return $this->get_success_response(
                '',
                200,
                array(
                    'task'    => 'summarize_contact',
                    'summary' => array(),
                    'cached'  => false,
                )
            );
        }

        $context = ContactSummaryContext::build( $contact_id, $locale );
        if ( is_wp_error( $context ) ) {
            return $this->get_error_response( $context->get_error_message(), 404 );
        }

        $hint      = trim( (string) ( $params['prompt'] ?? '' ) );
        $user_text = wp_json_encode( $context );
        if ( '' !== $hint ) {
            $user_text .= "\n\nUser focus: " . $hint;
        }

        $result = SingleShotAIService::generate( $this->contact_summary_system_prompt( $locale ), $user_text );
        if ( is_wp_error( $result ) ) {
            $data   = $result->get_error_data();
            $status = (int) ( is_array( $data ) ? ( $data['status'] ?? 0 ) : 0 );
            return $this->get_error_response( $result->get_error_message(), $status >= 400 ? $status : 422 );
        }

        $provider = AISettings::getActiveProvider();
        $summary  = array(
            'content'      => $result,
            'generated_at' => gmdate( 'c' ),
            'provider'     => $provider,
            'model'        => '' !== $provider ? AISettings::getModel( $provider ) : '',
            'locale'       => $locale,
            'counts'       => $context['counts'] ?? array(),
        );

        // No expiry: the summary stays until the contact is regenerated. It is
        // stored as a transient (not contact meta) so it never leaks into the
        // contact's custom-field payload or back into the AI context.
        set_transient( $cache_key, $summary, 0 );

        return $this->get_success_response(
            '',
            200,
            array(
                'task'    => 'summarize_contact',
                'summary' => $summary,
                'cached'  => false,
            )
        );
    }

    /**
     * Resolve the site locale the contact summary is written in.
     *
     * Uses Settings → General → Site Language (get_locale()), not the current
     * user's profile language, so every viewer sees a consistent, cacheable
     * summary. Falls back to en_US for deterministic cache comparison.
     *
     * @return string
     */
    private function contact_summary_locale() {
        $locale = sanitize_text_field( (string) get_locale() );
        return $locale ? $locale : 'en_US';
    }

    /**
     * Transient key for a contact summary, namespaced by contact and locale.
     *
     * @param int    $contact_id Contact ID.
     * @param string $locale     Site locale.
     * @return string
     */
    private function contact_summary_cache_key( $contact_id, $locale ) {
        return 'mint_ai_csum_' . (int) $contact_id . '_' . md5( $locale );
    }

    /**
     * Whether a cached summary can be reused for the current site locale.
     *
     * New summaries store their generation locale and are reusable only when it
     * matches. Legacy caches without a locale were English-only, so they are
     * valid only while the site locale is English.
     *
     * @param array  $summary Cached summary payload.
     * @param string $locale  Current site locale.
     * @return bool
     */
    private function is_cached_summary_valid( $summary, $locale ) {
        if ( ! is_array( $summary ) || empty( $summary['content'] ) ) {
            return false;
        }

        $cached_locale = isset( $summary['locale'] ) ? sanitize_text_field( $summary['locale'] ) : '';
        if ( '' !== $cached_locale ) {
            return $cached_locale === $locale;
        }

        return 0 === strpos( $locale, 'en' );
    }

    /**
     * Build the locale-aware system prompt for the contact summary.
     *
     * @param string $locale Site locale to write the summary in.
     * @return string
     */
    private function contact_summary_system_prompt( $locale ) {
        $prompt  = 'You are a CRM assistant writing an internal contact summary for sales and support teams inside an email marketing tool. ';
        $prompt .= 'Use ONLY the supplied contact data — never invent emails, purchases, dates, tags, or activity. If a section has no data, say it is not available. ';
        $prompt .= 'Do not restate basic profile fields (name, email, status, created date) unless they matter to a decision. ';
        $prompt .= 'Return markdown only: short decision-focused headings, concise bullets covering email engagement, purchase history when present, risk signals, and opportunities, ';
        $prompt .= 'then a final section starting with "Suggested next action:". ';
        $prompt .= 'Keep proper nouns, email subjects, tag and list names, order numbers, URLs, and IDs unchanged. ';
        $prompt .= 'Write the entire summary in the WordPress site language for locale ' . $locale . '.';

        /**
         * Filter the AI contact-summary system prompt.
         *
         * @since 1.20.0
         *
         * @param string $prompt The system prompt.
         * @param string $locale Site locale the summary is written in.
         */
        return apply_filters( 'mint_mail/ai/contact_summary_system_prompt', $prompt, $locale );
    }
}
