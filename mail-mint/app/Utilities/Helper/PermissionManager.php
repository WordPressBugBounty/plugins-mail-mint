<?php

/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2025-01-07 15:33:17
 * @modify date 2025-01-07 15:33:17
 * @package Mint\MRM\Utilities\Helper
 */

namespace Mint\MRM\Utilities\Helper;

/**
 * Permission Manager
 *
 * Summary: This class is responsible for handling permissions.
 * Description: This class is responsible for handling permissions.
 *
 * @since 1.16.0
 */
class PermissionManager{

    /**
     * Retrieves the readable permissions for the plugin.
     *
     * This function fetches the readable permissions for the plugin. It retrieves the permissions
     * from the database and returns them.
     *
     * @return array The readable permissions for the plugin.
     * 
     * @since 1.16.0
     */
    public static function get_readable_permissions(){
        return apply_filters('mailmint_readable_permissions', array(
            [
                'title' => __('Capabilities', 'mrm'),
                'permissions' => [
                    [
                        'label'   => __('Assign All', 'mrm'),
                        'value'   => 'assign_all',
                        'depends' => [],
                    ],
                ],
                'tooltip' => true,
                'tooltipText' => 'Check all capabilities',
            ],
            [
                'title' => 'Dashboard',
                'permissions' => [
                    [
                        'label'   => 'Mail Mint Dashboard',
                        'value'   => 'mint_view_dashboard',
                        'depends' => [],
                    ],
                ],
            ],
            [
                'title' => 'Contacts',
                'permissions' => [
                    [
                        'label'   => 'Read',
                        'value'   => 'mint_read_contacts',
                        'depends' => [],
                    ],
                    [
                        'label'   => 'Add/Update/Import',
                        'value'   => 'mint_manage_contacts',
                        'depends' => [
                            'mint_read_contacts'
                        ],
                    ],
                    [
                        'label'   => 'Delete',
                        'value'   => 'mint_manage_contacts_delete',
                        'depends' => [
                            'mint_read_contacts'
                        ],
                    ],
                    [
                        'label'   => 'Export',
                        'value'   => 'mint_manage_contacts_export',
                        'depends' => [
                            'mint_read_contacts'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Segments',
                'permissions' => [
                    [
                        'label' => 'Lists/Tags/Segments Add or Update',
                        'value' => 'mint_manage_contact_cats',
                        'depends' => [
                            'mint_read_contacts'
                        ],
                    ],
                    [
                        'label' => 'Lists/Tags/Segments Delete',
                        'value' => 'mint_manage_contact_cats_delete',
                        'depends' => [
                            'mint_read_contacts'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Campaigns',
                'permissions' => [
                    [
                        'label' => 'Read',
                        'value' => 'mint_read_campaigns',
                        'depends' => [],
                    ],
                    [
                        'label' => 'Add/Update/Duplicate',
                        'value' => 'mint_manage_campaigns',
                        'depends' => [
                            'mint_read_campaigns'
                        ],
                    ],
                    [
                        'label' => 'Email Send',
                        'value' => 'mint_manage_campaigns_send',
                        'depends' => [
                            'mint_read_campaigns',
                            'mint_manage_campaigns'
                        ],
                    ],
                    [
                        'label' => 'Delete',
                        'value' => 'mint_manage_campaigns_delete',
                        'depends' => [
                            'mint_read_campaigns'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Automations',
                'permissions' => [
                    [
                        'label' => 'Read',
                        'value' => 'mint_read_automations',
                        'depends' => [],
                    ],
                    [
                        'label' => 'Add/Update/Import/Duplicate',
                        'value' => 'mint_manage_automations',
                        'depends' => [
                            'mint_read_automations'
                        ],
                    ],
                    [
                        'label' => 'Export',
                        'value' => 'mint_manage_automations_export',
                        'depends' => [
                            'mint_read_automations'
                        ],
                    ],
                    [
                        'label' => 'Delete',
                        'value' => 'mint_manage_automations_delete',
                        'depends' => [
                            'mint_read_automations'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Forms',
                'permissions' => [
                    [
                        'label' => 'Read',
                        'value' => 'mint_read_forms',
                        'depends' => [],
                    ],
                    [
                        'label' => 'Add/Update/Import/Duplicate',
                        'value' => 'mint_manage_forms',
                        'depends' => [
                            'mint_read_forms'
                        ],
                    ],
                    [
                        'label' => 'Export',
                        'value' => 'mint_manage_forms_export',
                        'depends' => [
                            'mint_read_forms'
                        ],
                    ],
                    [
                        'label' => 'Delete',
                        'value' => 'mint_manage_forms_delete',
                        'depends' => [
                            'mint_read_forms'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Email Templates',
                'permissions' => [
                    [
                        'label' => 'Manage Email Templates',
                        'value' => 'mint_manage_email_templates',
                        'depends' => [],
                    ],
                ],
            ],
            [
                'title' => 'Tools',
                'permissions' => [
                    [
                        'label' => 'Read',
                        'value' => 'mint_read_tools',
                        'depends' => [],
                    ],
                    [
                        'label' => 'Manage Link Triggers',
                        'value' => 'mint_manage_link_triggers',
                        'depends' => [
                            'mint_read_tools'
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Settings',
                'permissions' => [
                    [
                        'label' => 'Manage Settings',
                        'value' => 'mint_manage_settings',
                        'depends' => [],
                    ],
                ],
            ],
            [
                'title' => 'Integrations',
                'permissions' => [
                    [
                        'label' => 'Manage Integrations',
                        'value' => 'mint_manage_integrations',
                        'depends' => [],
                    ],
                ],
            ],
            [
                'title' => 'License',
                'permissions' => [
                    [
                        'label' => 'Manage License',
                        'value' => 'mint_manage_license',
                        'depends' => [],
                    ],
                ],
            ],
        ));
    }

    /**
     * Retrieves the permissions for the plugin.
     *
     * This function fetches the permissions for the plugin. It retrieves the permissions
     * from the database and returns them.
     *
     * @return array The plugin's permissions.
     * 
     * @since 1.16.0
     */
    public static function plugin_permissions(){
        return apply_filters('mailmint_plugin_permissions', [
            'mint_view_dashboard',
            'mint_read_contacts',
            'mint_manage_contacts',
            'mint_manage_contacts_delete',
            'mint_manage_contacts_export',
            'mint_manage_contact_cats',
            'mint_manage_contact_cats_delete',
            'mint_read_campaigns',
            'mint_manage_campaigns',
            'mint_manage_campaigns_send',
            'mint_manage_campaigns_delete',
            'mint_read_automations',
            'mint_manage_automations',
            'mint_manage_automations_export',
            'mint_manage_automations_delete',
            'mint_read_forms',
            'mint_manage_forms',
            'mint_manage_forms_export',
            'mint_manage_forms_delete',
            'mint_manage_email_templates',
            'mint_read_tools',
            'mint_manage_link_triggers',
            'mint_manage_settings',
            'mint_manage_integrations',
            'mint_manage_license',
        ]);
    }

    /**
     * Attaches permissions to an entity.
     * 
     * This function attaches permissions to an entity. It removes all the permissions
     *
     * @param object $entity The entity to attach the permissions to.
     * @param array $permissions The permissions to attach.
     * @return void
     * 
     * @since 1.16.0
     */    
    public static function attach_permissions($entity, $permissions){
        $all_permissions = self::plugin_permissions();

        foreach ($all_permissions as $permission) {
            $entity->remove_cap($permission);
        }

        $permissions = array_intersect($all_permissions, $permissions);

        foreach ($permissions as $permission) {
            $entity->add_cap($permission);
        }
    }

    /**
     * Assigns capabilities to the administrator.
     *
     * This function assigns capabilities to the administrator. It removes all the capabilities
     * from the administrator role and then adds the custom capabilities.
     *
     * @return void
     * 
     * @since 1.16.0
     */
    public static function assign_capabilities_to_admin() {
        // Get the administrator role
        $role = get_role('administrator');
    
        // Bail out if the administrator role doesn't exist
        if (!$role) {
            return;
        }
    
        // Get the custom capabilities
        $all_permissions = self::plugin_permissions();

        // Remove each capability from the administrator role.
        foreach ($all_permissions as $permission) {
            $role->remove_cap($permission);
        }
    
        // Add each capability to the administrator role
        foreach ($all_permissions as $permission) {
            $role->add_cap($permission);
        }
    }

    /**
     * Retrieves the permissions for a given user.
     *
     * This function fetches the permissions for a specified user. If a user ID is provided,
     * it retrieves the user object based on the ID.
     *
     * @param int|WP_User|false $user Optional. User ID or WP_User object. Default false.
     * @return array The user's permissions.
     * 
     * @since 1.16.0
     */
    public static function get_user_permissions($user = false){
        if (is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }

        if (!$user) {
            return [];
        }

        $plugin_permission = self::plugin_permissions();

        if ($user->has_cap('manage_options')) {
            $plugin_permission[] = 'administrator';
            $permissions = $plugin_permission;
        } else {
            $permissions = array_values(array_intersect(array_keys($user->allcaps), $plugin_permission));
        }

        $permissions = apply_filters('mailmint_user_permissions', $permissions, $user);
        return array_values($permissions);
    }

    /**
     * Retrieves the permissions for a given role.
     *
     * This function fetches the permissions for a specified role. If a role name is provided,
     * it retrieves the role object based on the name.
     *
     * @param string $role The role name.
     * @return array The role's permissions.
     * 
     * @since 1.16.0
     */
    public static function get_role_permissions($role){
        $role = get_role($role);
        if (!$role) {
            return [];
        }

        $permissions       = array_keys($role->capabilities);
        $plugin_permission = self::plugin_permissions();

        $permissions = array_values(array_intersect($permissions, $plugin_permission));
        $permissions = apply_filters('mailmint_role_permissions', $permissions, $role);
        return $permissions;
    }

    /**
     * Retrieves the current user's permissions.
     *
     * This function returns the permissions of the current user. If the permissions
     * are already cached and the $cached parameter is true, it returns the cached
     * permissions. Otherwise, it fetches the permissions from the database.
     *
     * @param bool $cached Optional. Whether to use cached permissions. Default true.
     * @return array The current user's permissions.
     * 
     * @since 1.16.0
     */
    public static function current_user_permissions($cached = true){
        static $permissions;

        if ($permissions && $cached) {
            return $permissions;
        }
        
        $permissions = self::get_user_permissions(get_current_user_id());

        return $permissions;
    }

    /**
     * Checks if the current user has a permission.
     *
     * This function checks if the current user has a specified permission. If the current user
     * is an administrator, it returns true. Otherwise, it checks if the user has the permission.
     *
     * @param string $permission The permission to check.
     * @return bool Whether the current user has the permission.
     * 
     * @since 1.16.0
     */
    public static function current_user_can($permission){
        $capability = is_multisite() ? 'delete_sites' : $permission;

        // If the user is an administrator, return true
        if (current_user_can('manage_options')) {
            return function () use ($capability) {
                return apply_filters('mailmint_current_admin_can', true, $capability);
            };
        }

        return function () use ($capability) {
            if ( !current_user_can( $capability ) ) {
                return new \WP_Error(rest_authorization_required_code(), __('Sorry, you are not authorized to perform this action.', 'mrm'), ['status' => 'mail_mint_access_denied']);
            }
            return true;
        };
    }
}
