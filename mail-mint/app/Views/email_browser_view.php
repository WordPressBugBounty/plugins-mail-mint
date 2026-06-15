<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo esc_html( $data['email_subject'] ); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #f4f4f4;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
        }
        .mrm-browser-view-bar {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 24px;
            text-align: center;
            font-size: 13px;
            color: #666666;
        }
        .mrm-email-wrapper {
            max-width: 800px;
            margin: 24px auto;
            background: #ffffff;
        }
        #mrm_email_body { display: block; }
        @media (max-width: 600px) {
            .mrm-email-wrapper { margin: 0; }
        }

        /* ── Share toolbar ─────────────────────────────────────────── */
        .mrm-share-bar {
            background: #ffffff;
            border-bottom: 1px solid #e4e6eb;
            padding: 16px 24px;
        }
        .mrm-share-bar__inner {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }
        .mrm-share-bar__text {
            flex-shrink: 0;
            min-width: 200px;
        }
        .mrm-share-bar__title {
            font-size: 15px;
            font-weight: 700;
            color: #1a1f36;
            margin: 0 0 4px;
        }
        .mrm-share-bar__desc {
            font-size: 13px;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
        }
        .mrm-share-bar__actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        .mrm-share-bar__url-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mrm-share-bar__url {
            flex: 1;
            min-width: 0;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            color: #374151;
            background: #ffffff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mrm-share-bar__copy {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            white-space: nowrap;
            flex-shrink: 0;
            transition: background 0.15s, color 0.15s;
        }
        .mrm-share-bar__copy:hover { background: #f3f4f6; }
        .mrm-share-bar__copy--copied { color: #16a34a; border-color: #16a34a; }
        .mrm-share-bar__social {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mrm-share-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            flex: 1;
            height: 38px;
            padding: 0 14px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: opacity 0.15s;
        }
        .mrm-share-btn:hover { opacity: 0.88; color: #ffffff; }
        .mrm-share-btn--fb   { background: #1877f2; }
        .mrm-share-btn--x    { background: #000000; }
        .mrm-share-btn--wa   { background: #25d366; }
        .mrm-share-btn--mail { background: #4f46e5; }
        @media (max-width: 680px) {
            .mrm-share-bar__inner { flex-direction: column; align-items: flex-start; }
            .mrm-share-bar__actions { width: 100%; }
            .mrm-share-bar__url-wrap { width: 100%; }
        }
    </style>
    <?php
    /**
     * Fires inside the <head> of the browser view page.
     *
     * @since 1.22.0
     * @param array $data Template data (email_body, email_subject).
     */
    do_action( 'mail_mint/view_in_browser_head', $data );
    ?>
</head>
<body>

<div class="mrm-browser-view-bar">
    <?php echo esc_html( $data['email_subject'] ); ?>
</div>

<?php if ( ! empty( $data['show_share_bar'] ) && ! empty( $data['archive_url'] ) ) : ?>
<div class="mrm-share-bar">
    <div class="mrm-share-bar__inner">
    <div class="mrm-share-bar__text">
        <p class="mrm-share-bar__title"><?php esc_html_e( 'Share this email', 'mrm' ); ?></p>
        <p class="mrm-share-bar__desc"><?php esc_html_e( 'Copy the public link or share it on your favorite channel.', 'mrm' ); ?></p>
    </div>
    <div class="mrm-share-bar__actions">
        <div class="mrm-share-bar__url-wrap">
            <input
                id="mrm-share-url"
                class="mrm-share-bar__url"
                type="text"
                value="<?php echo esc_attr( (string) $data['archive_url'] ); ?>"
                readonly
            />
            <button class="mrm-share-bar__copy" id="mrm-copy-btn" onclick="mrmCopyLink()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                <span id="mrm-copy-label"><?php esc_html_e( 'Copy link', 'mrm' ); ?></span>
            </button>
        </div>
        <div class="mrm-share-bar__social">
            <a class="mrm-share-btn mrm-share-btn--fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( (string) $data['archive_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                Facebook
            </a>
            <a class="mrm-share-btn mrm-share-btn--x" href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( (string) $data['archive_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                X
            </a>
            <a class="mrm-share-btn mrm-share-btn--wa" href="https://api.whatsapp.com/send?text=<?php echo rawurlencode( (string) $data['archive_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                WhatsApp
            </a>
            <a class="mrm-share-btn mrm-share-btn--mail" href="mailto:?subject=<?php echo rawurlencode( (string) $data['email_subject'] ); ?>&body=<?php echo rawurlencode( (string) $data['archive_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,12 2,6"></polyline></svg>
                Email
            </a>
        </div>
    </div>
    </div>
</div>
<?php endif; ?>

<?php
/**
 * Fires before the email body in the browser view.
 *
 * @since 1.22.0
 * @param array $data Template data.
 */
do_action( 'mail_mint/view_in_browser_before_body', $data );
?>

<div class="mrm-email-wrapper">
    <div id="mrm_email_body"></div>
</div>

<?php
/**
 * Fires after the email body in the browser view.
 *
 * @since 1.22.0
 * @param array $data Template data.
 */
do_action( 'mail_mint/view_in_browser_after_body', $data );
?>

<script src="<?php echo esc_url( MRM_PLUGIN_URL . 'assets/admin/purify.min.js' ); ?>"></script>
<script type="text/javascript">
    var mrmEmailBody = <?php echo wp_json_encode( $data['email_body'] ); ?>;

    var host = document.querySelector('#mrm_email_body');
    var shadow = host.attachShadow({ mode: 'closed' });
    var div = document.createElement('div');
    div.innerHTML = DOMPurify.sanitize(mrmEmailBody, { ADD_TAGS: ['style'], ADD_ATTR: ['target'] });
    shadow.appendChild(div);

    function mrmCopyLink() {
        var input = document.getElementById('mrm-share-url');
        var label = document.getElementById('mrm-copy-label');
        var btn   = document.getElementById('mrm-copy-btn');
        if (!input) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(input.value).then(function() {
                label.textContent = '<?php echo esc_js( __( 'Copied!', 'mrm' ) ); ?>';
                btn.classList.add('mrm-share-bar__copy--copied');
                setTimeout(function() {
                    label.textContent = '<?php echo esc_js( __( 'Copy link', 'mrm' ) ); ?>';
                    btn.classList.remove('mrm-share-bar__copy--copied');
                }, 2000);
            });
        } else {
            input.select();
            document.execCommand('copy');
        }
    }
</script>
</body>
</html>
