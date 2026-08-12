<?php
$page_title = 'Launch Control';
$activePage = 'launch-control';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
ensureFeatureFlagsSchema($db);
ensureNavigationControlsSchema($db);

$success_message = '';
$error_message = '';
$current_admin_id = (int) ($current_admin['id'] ?? 0);
$current_tab = (string) ($_GET['tab'] ?? $_POST['current_tab'] ?? 'features');
if (!in_array($current_tab, ['features', 'navigation'], true)) {
    $current_tab = 'features';
}
$current_level = (string) ($_GET['level'] ?? $_POST['current_level'] ?? 'visitor');
$allowed_levels = ['visitor', 'beginner', 'pro', 'expert'];
if (!in_array($current_level, $allowed_levels, true)) {
    $current_level = 'visitor';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_flags') {
            $defaults = getDefaultFeatureFlags();
            foreach ($defaults as $key => $default_flag) {
                $posted = $_POST['features'][$key] ?? [];
                if (!is_array($posted)) {
                    $posted = [];
                }

                $label = trim((string) ($posted['label'] ?? $default_flag['label']));
                $fallback_title = trim((string) ($posted['fallback_title'] ?? $default_flag['fallback_title']));
                $fallback_message = trim((string) ($posted['fallback_message'] ?? $default_flag['fallback_message']));
                $fallback_cta_label = trim((string) ($posted['fallback_cta_label'] ?? $default_flag['fallback_cta_label']));
                $fallback_cta_url = trim((string) ($posted['fallback_cta_url'] ?? $default_flag['fallback_cta_url']));

                $stmt = $db->prepare("
                    UPDATE feature_flags
                    SET label = ?,
                        feature_group = ?,
                        is_visible = ?,
                        is_accessible = ?,
                        fallback_title = ?,
                        fallback_message = ?,
                        fallback_cta_label = ?,
                        fallback_cta_url = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE feature_key = ?
                ");
                $stmt->execute([
                    $label !== '' ? $label : (string) $default_flag['label'],
                    (string) $default_flag['group'],
                    !empty($posted['is_visible']) ? 1 : 0,
                    !empty($posted['is_accessible']) ? 1 : 0,
                    $fallback_title !== '' ? $fallback_title : (string) $default_flag['fallback_title'],
                    $fallback_message !== '' ? $fallback_message : (string) $default_flag['fallback_message'],
                    $fallback_cta_label !== '' ? $fallback_cta_label : (string) $default_flag['fallback_cta_label'],
                    $fallback_cta_url !== '' ? $fallback_cta_url : (string) $default_flag['fallback_cta_url'],
                    $current_admin_id > 0 ? $current_admin_id : null,
                    $key,
                ]);
            }
            $success_message = 'Launch controls updated successfully.';
        } elseif ($action === 'restore_defaults') {
            $reset_messages = !empty($_POST['reset_messages']);
            seedDefaultFeatureFlags($db, $reset_messages);
            $success_message = $reset_messages
                ? 'MVP defaults restored, including fallback messages.'
                : 'MVP defaults restored. Custom fallback messages were preserved.';
        } elseif ($action === 'save_navigation_slots') {
            $submit_level = (string) ($_POST['current_level'] ?? 'visitor');
            if (!in_array($submit_level, ['visitor', 'beginner', 'pro', 'expert'], true)) {
                $submit_level = 'visitor';
            }
            $level_audience_map = ['visitor' => 'guest', 'beginner' => 'member', 'pro' => 'member', 'expert' => 'member'];
            $submit_audience = (string) ($level_audience_map[$submit_level] ?? 'guest');

            $desktop_limit = 6;
            $mobile_limit = 4;
            $mobile_more_limit = 4;

            $desktop_slot_group = 'desktop_' . $submit_level;
            $mobile_slot_group = 'mobile_' . $submit_level;

            $desktop_slots = $_POST['desktop_slots'] ?? [];
            if (!is_array($desktop_slots)) {
                $desktop_slots = [];
            }
            $mobile_slots = $_POST['mobile_slots'] ?? [];
            if (!is_array($mobile_slots)) {
                $mobile_slots = [];
            }

            $mobile_more_slots = $_POST['mobile_more_slots'] ?? [];
            if (!is_array($mobile_more_slots)) {
                $mobile_more_slots = [];
            }

            $configs = [
                [$desktop_slots, $desktop_slot_group, 'header', 'primary', $submit_audience, $desktop_limit],
                [$mobile_slots, $mobile_slot_group, 'mobile', 'bottom', $submit_audience, $mobile_limit],
                [$mobile_more_slots, 'mobile_more_' . $submit_level, 'mobile_more', 'more', $submit_audience, $mobile_more_limit],
            ];

            foreach ($configs as [$slots, $slot_group, $loc, $sec, $aud, $limit]) {
                for ($slot = 1; $slot <= (int) $limit; $slot++) {
                    $selected_key = trim((string) ($slots[$slot] ?? ''));
                    if ($selected_key !== '') {
                        $db->prepare("UPDATE navigation_controls SET is_enabled = 1, updated_by = ?, updated_at = NOW() WHERE nav_key = ?")
                            ->execute([
                                $current_admin_id > 0 ? $current_admin_id : null,
                                $selected_key,
                            ]);
                    }
                    $db->prepare("
                        INSERT INTO navigation_slots (
                            slot_group, location, section_key, audience, user_level, slot_number, nav_key, updated_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            location = VALUES(location),
                            section_key = VALUES(section_key),
                            audience = VALUES(audience),
                            nav_key = VALUES(nav_key),
                            updated_by = VALUES(updated_by),
                            updated_at = NOW()
                    ")
                        ->execute([
                            (string) $slot_group,
                            (string) $loc,
                            (string) $sec,
                            (string) $aud,
                            (string) $submit_level,
                            $slot,
                            $selected_key,
                            $current_admin_id > 0 ? $current_admin_id : null,
                        ]);
                }
            }

            getNavigationControlRegistry(true);
            $level_labels = ['visitor' => 'Visitor', 'beginner' => 'Beginner', 'pro' => 'Pro', 'expert' => 'Expert'];
            $success_message = ($level_labels[$submit_level] ?? $submit_level) . ' navigation slots updated successfully.';
        } elseif ($action === 'save_navigation') {
            $registry = getNavigationControlRegistry();
            foreach ($registry as $key => $default_item) {
                $posted = $_POST['nav_items'][$key] ?? [];
                if (!is_array($posted)) {
                    $posted = [];
                }

                $label = trim((string) ($posted['label'] ?? $default_item['label']));
                $custom_url = trim((string) ($posted['custom_url'] ?? ''));
                $icon_class = trim((string) ($posted['icon_class'] ?? ($default_item['icon_class'] ?? '')));
                $badge_text = trim((string) ($posted['badge_text'] ?? ($default_item['badge_text'] ?? '')));
                $sort_order = isset($posted['sort_order']) ? (int) $posted['sort_order'] : (int) ($default_item['sort_order'] ?? 0);

                $stmt = $db->prepare("
                    UPDATE navigation_controls
                    SET label = ?,
                        custom_url = ?,
                        icon_class = ?,
                        badge_text = ?,
                        sort_order = ?,
                        is_enabled = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE nav_key = ?
                ");
                $stmt->execute([
                    $label !== '' ? $label : (string) $default_item['label'],
                    $custom_url,
                    $icon_class,
                    $badge_text,
                    $sort_order,
                    !empty($posted['is_enabled']) ? 1 : 0,
                    $current_admin_id > 0 ? $current_admin_id : null,
                    $key,
                ]);
            }
            getNavigationControlRegistry(true);
            $success_message = 'Navigation controller updated successfully.';
        } elseif ($action === 'create_navigation_item') {
            $label = trim((string) ($_POST['new_nav']['label'] ?? ''));
            $location = trim((string) ($_POST['new_nav']['location'] ?? ''));
            $section_key = trim((string) ($_POST['new_nav']['section_key'] ?? ''));
            $custom_url = trim((string) ($_POST['new_nav']['custom_url'] ?? ''));
            $icon_class = trim((string) ($_POST['new_nav']['icon_class'] ?? ''));
            $badge_text = trim((string) ($_POST['new_nav']['badge_text'] ?? ''));
            $audience = trim((string) ($_POST['new_nav']['audience'] ?? 'all'));
            $sort_order = (int) ($_POST['new_nav']['sort_order'] ?? 100);
            $item_type = trim((string) ($_POST['new_nav']['item_type'] ?? 'link'));
            $children_section_key = trim((string) ($_POST['new_nav']['children_section_key'] ?? ''));
            $new_section_key = trim((string) ($_POST['new_nav']['new_section_key'] ?? ''));

            $allowed_sections = [
                'header' => ['primary', 'resources', 'marketplace'],
                'footer' => ['platform', 'resources', 'legal', 'bottom'],
                'mobile' => ['bottom'],
            ];

            if ($label === '') {
                throw new RuntimeException('Label is required for a new navigation item.');
            }
            if (!in_array($item_type, ['link', 'dropdown'], true)) {
                $item_type = 'link';
            }
            if ($item_type === 'link' && $custom_url === '') {
                throw new RuntimeException('Custom URL is required for a link navigation item.');
            }

            $is_new_section = false;

            // Handle "create new section" selection
            if ($section_key === '__new__' || $children_section_key === '__new__') {
                if ($new_section_key === '') {
                    throw new RuntimeException('Please enter a name for the new section.');
                }
                if (!preg_match('/^[a-z0-9_]{2,40}$/', $new_section_key)) {
                    throw new RuntimeException('Section key must be 2-40 characters using only lowercase letters, numbers, and underscores.');
                }
                if ($item_type === 'dropdown' && $children_section_key === '__new__') {
                    $children_section_key = $new_section_key;
                    $is_new_section = true;
                } elseif ($item_type === 'link' && $section_key === '__new__') {
                    $section_key = $new_section_key;
                    $is_new_section = true;
                }
            } elseif ($new_section_key !== '') {
                // User typed a new section key directly
                if (!preg_match('/^[a-z0-9_]{2,40}$/', $new_section_key)) {
                    throw new RuntimeException('Section key must be 2-40 characters using only lowercase letters, numbers, and underscores.');
                }
                if ($item_type === 'dropdown') {
                    $children_section_key = $new_section_key;
                    $is_new_section = true;
                } else {
                    $section_key = $new_section_key;
                    $is_new_section = true;
                }
            }

            if ($item_type === 'dropdown' && $children_section_key === '') {
                throw new RuntimeException('A children section is required for a dropdown navigation item.');
            }
            if ($section_key === '__new__' || $children_section_key === '__new__') {
                throw new RuntimeException('Please enter a name for the new section.');
            }
            if (!$is_new_section) {
                $registry = getNavigationControlRegistry();
                $section_exists = false;
                foreach ($registry as $reg_item) {
                    if ((string) ($reg_item['location'] ?? '') === $location
                        && (string) ($reg_item['section_key'] ?? '') === $section_key) {
                        $section_exists = true;
                        break;
                    }
                }
                if (!$section_exists && (!isset($allowed_sections[$location]) || !in_array($section_key, $allowed_sections[$location], true))) {
                    throw new RuntimeException('Please choose a valid navigation section.');
                }
            }
            if (!in_array($audience, ['all', 'guest', 'member'], true)) {
                $audience = 'all';
            }
            if ($location === 'mobile' && $icon_class === '') {
                $icon_class = 'fas fa-circle';
            }

            $nav_key = coinrexGenerateNavigationKey($location, $section_key, $label);
            $insert = $db->prepare("
                INSERT INTO navigation_controls (
                    nav_key, location, section_key, label, custom_url, icon_class, badge_text, sort_order, is_enabled,
                    audience, item_type, children_section_key, admin_hint, admin_route_hint, is_system, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 0, ?)
            ");
            $insert->execute([
                $nav_key,
                $location,
                $section_key,
                $label,
                $custom_url,
                $icon_class,
                $badge_text,
                $sort_order,
                $audience,
                $item_type,
                $children_section_key,
                $item_type === 'dropdown'
                    ? 'Custom dropdown menu created from Launch Control. Children are managed in the ' . $children_section_key . ' section.'
                    : 'Custom navigation item created from Launch Control.',
                $item_type === 'dropdown' ? 'Dropdown only' : $custom_url,
                $current_admin_id > 0 ? $current_admin_id : null,
            ]);
            getNavigationControlRegistry(true);
            $success_message = $item_type === 'dropdown'
                ? 'New dropdown menu added successfully. Add children in the ' . $children_section_key . ' section.'
                : 'New navigation item added successfully.';
        } elseif ($action === 'assign_navigation_child') {
            $link_key = trim((string) ($_POST['assign_child']['link_key'] ?? ''));
            $dropdown_key = trim((string) ($_POST['assign_child']['dropdown_key'] ?? ''));

            if ($link_key === '' || $dropdown_key === '') {
                throw new RuntimeException('Please select both a link and a dropdown menu.');
            }

            $registry = getNavigationControlRegistry();
            $link_item = $registry[$link_key] ?? null;
            $dropdown_item = $registry[$dropdown_key] ?? null;

            if (!is_array($link_item)) {
                throw new RuntimeException('Selected link was not found.');
            }
            if (!is_array($dropdown_item)) {
                throw new RuntimeException('Selected dropdown menu was not found.');
            }
            if ((string) ($dropdown_item['item_type'] ?? 'link') !== 'dropdown') {
                throw new RuntimeException('Selected item is not a dropdown menu.');
            }
            if ((string) ($link_item['item_type'] ?? 'link') === 'dropdown') {
                throw new RuntimeException('Cannot add a dropdown as a child of another dropdown.');
            }

            $children_section = (string) ($dropdown_item['children_section_key'] ?? '');
            if ($children_section === '') {
                throw new RuntimeException('Selected dropdown has no children section configured.');
            }

            $link_location = (string) ($link_item['location'] ?? '');
            $dropdown_location = (string) ($dropdown_item['location'] ?? '');
            if ($link_location !== $dropdown_location) {
                throw new RuntimeException('Link and dropdown must be in the same location (e.g. both Header).');
            }

            // Check if link is already a child of this dropdown
            if ((string) ($link_item['section_key'] ?? '') === $children_section) {
                throw new RuntimeException('This link is already a child of the selected dropdown.');
            }

            // Update the link's section_key to the dropdown's children section
            $stmt = $db->prepare("
                UPDATE navigation_controls
                SET section_key = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE nav_key = ?
            ");
            $stmt->execute([
                $children_section,
                $current_admin_id > 0 ? $current_admin_id : null,
                $link_key,
            ]);

            getNavigationControlRegistry(true);
            $success_message = '"' . (string) ($link_item['label'] ?? $link_key) . '" added to "' . (string) ($dropdown_item['label'] ?? $dropdown_key) . '" dropdown.';
        } elseif ($action === 'edit_navigation_item') {
            $nav_key = trim((string) ($_POST['edit_nav']['nav_key'] ?? ''));
            if ($nav_key === '') {
                throw new RuntimeException('Navigation item key is missing.');
            }

            $registry = getNavigationControlRegistry();
            $existing_item = $registry[$nav_key] ?? null;
            if (!is_array($existing_item)) {
                throw new RuntimeException('Navigation item was not found.');
            }

            $label = trim((string) ($_POST['edit_nav']['label'] ?? ''));
            $custom_url = trim((string) ($_POST['edit_nav']['custom_url'] ?? ''));
            $icon_class = trim((string) ($_POST['edit_nav']['icon_class'] ?? ''));
            $badge_text = trim((string) ($_POST['edit_nav']['badge_text'] ?? ''));
            $sort_order = (int) ($_POST['edit_nav']['sort_order'] ?? 100);
            $is_enabled = !empty($_POST['edit_nav']['is_enabled']) ? 1 : 0;
            $item_type = (string) ($existing_item['item_type'] ?? 'link');

            if ($label === '') {
                throw new RuntimeException('Label is required for a navigation item.');
            }
            if ($item_type === 'link' && $custom_url === '') {
                // Keep existing custom_url if not provided
                $custom_url = trim((string) ($existing_item['custom_url'] ?? ''));
                if ($custom_url === '') {
                    throw new RuntimeException('Custom URL is required for a link navigation item.');
                }
            }

            $stmt = $db->prepare("
                UPDATE navigation_controls
                SET label = ?,
                    custom_url = ?,
                    icon_class = ?,
                    badge_text = ?,
                    sort_order = ?,
                    is_enabled = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE nav_key = ?
            ");
            $stmt->execute([
                $label,
                $custom_url,
                $icon_class,
                $badge_text,
                $sort_order,
                $is_enabled,
                $current_admin_id > 0 ? $current_admin_id : null,
                $nav_key,
            ]);

            getNavigationControlRegistry(true);
            $success_message = 'Navigation item "' . $label . '" updated successfully.';
        } elseif ($action === 'delete_navigation_item') {
            $nav_key = trim((string) ($_POST['nav_key'] ?? ''));
            if ($nav_key === '') {
                throw new RuntimeException('Navigation item key is missing.');
            }

            $delete = $db->prepare("DELETE FROM navigation_controls WHERE nav_key = ? AND is_system = 0 LIMIT 1");
            $delete->execute([$nav_key]);
            getNavigationControlRegistry(true);
            $success_message = 'Custom navigation item deleted successfully.';
        } elseif ($action === 'restore_navigation_defaults') {
            seedDefaultNavigationControls($db, true, true);
            getNavigationControlRegistry(true);
            $success_message = 'Navigation controller defaults restored.';
        } else {
            $error_message = 'Invalid launch control action.';
        }
    } catch (Throwable $e) {
        $error_message = $e->getMessage();
    }
}

$flags_stmt = $db->query("SELECT * FROM feature_flags ORDER BY FIELD(feature_group, 'MVP Core', 'Rewards', 'Marketplace', 'Developer'), id ASC");
$flags = $flags_stmt ? ($flags_stmt->fetchAll() ?: []) : [];
$grouped_flags = [];
$summary = [
    'total' => 0,
    'live' => 0,
    'hidden' => 0,
    'fallback' => 0,
    'off' => 0,
];
foreach ($flags as $flag) {
    $grouped_flags[(string) ($flag['feature_group'] ?? 'General')][] = $flag;
    $summary['total']++;
    $visible = !empty($flag['is_visible']);
    $accessible = !empty($flag['is_accessible']);
    if ($visible && $accessible) {
        $summary['live']++;
    } elseif (!$visible && $accessible) {
        $summary['hidden']++;
    } elseif ($visible && !$accessible) {
        $summary['fallback']++;
    } else {
        $summary['off']++;
    }
}

$feature_flags_by_key = [];
foreach ($flags as $flag) {
    $feature_flags_by_key[(string) ($flag['feature_key'] ?? '')] = $flag;
}

$status_meta = static function (array $flag): array {
    $visible = !empty($flag['is_visible']);
    $accessible = !empty($flag['is_accessible']);
    if ($visible && $accessible) {
        return ['label' => 'Live', 'class' => 'is-live', 'hint' => 'Visible and accessible'];
    }
    if (!$visible && $accessible) {
        return ['label' => 'Hidden', 'class' => 'is-hidden', 'hint' => 'Direct URL still works'];
    }
    if ($visible && !$accessible) {
        return ['label' => 'Fallback', 'class' => 'is-fallback', 'hint' => 'Shown, but opens fallback'];
    }
    return ['label' => 'Fully Off', 'class' => 'is-off', 'hint' => 'Hidden and blocked'];
};

$navigation_registry = getNavigationControlRegistry();
$navigation_grouped = [];
$navigation_summary = [
    'total' => 0,
    'enabled' => 0,
    'disabled' => 0,
    'feature_blocked' => 0,
];
foreach ($navigation_registry as $nav_item) {
    $group_key = (string) ($nav_item['section_label'] ?? (($nav_item['location'] ?? 'Navigation') . ' / ' . ($nav_item['section_key'] ?? 'default')));
    $navigation_grouped[$group_key][] = $nav_item;
    $navigation_summary['total']++;
    if (!empty($nav_item['is_enabled'])) {
        $navigation_summary['enabled']++;
    } else {
        $navigation_summary['disabled']++;
    }
    $linked_feature_key = (string) ($nav_item['feature_key'] ?? '');
    if ($linked_feature_key !== '' && empty($feature_flags_by_key[$linked_feature_key]['is_visible'])) {
        $navigation_summary['feature_blocked']++;
    }
}

$mobile_guest_preview = getManagedNavigationItems('mobile', 'bottom', [
    'is_logged_in' => false,
    'current_page' => '',
    'user_level' => 'beginner',
    'taskhub_mission_completed' => false,
]);
$mobile_member_preview = getManagedNavigationItems('mobile', 'bottom', [
    'is_logged_in' => true,
    'current_page' => '',
    'user_level' => 'beginner',
    'taskhub_mission_completed' => false,
]);
$mobile_guest_preview = array_slice($mobile_guest_preview, 0, 5);
$mobile_member_preview = array_slice($mobile_member_preview, 0, 5);
$mobile_preview_slots = static function (array $items): array {
    $slots = [];
    for ($index = 0; $index < 5; $index++) {
        $slots[] = $items[$index] ?? null;
    }

    return $slots;
};
$mobile_group_counts = [
    'enabled' => 0,
    'with_icon' => 0,
];
foreach ($navigation_registry as $nav_item) {
    if ((string) ($nav_item['location'] ?? '') !== 'mobile' || (string) ($nav_item['section_key'] ?? '') !== 'bottom') {
        continue;
    }
    if (!empty($nav_item['is_enabled'])) {
        $mobile_group_counts['enabled']++;
        if (trim((string) ($nav_item['icon_class'] ?? '')) !== '') {
            $mobile_group_counts['with_icon']++;
        }
    }
}

$navigation_slot_options = static function (array $registry, string $slotGroup, string $targetAudience, array $featureFlagsByKey): array {
    $options = [];
    foreach ($registry as $key => $item) {
        $itemType = (string) ($item['item_type'] ?? 'link');
        $itemLocation = (string) ($item['location'] ?? '');
        $itemSection = (string) ($item['section_key'] ?? '');

        $includeInSlotGroup = false;
        if ($slotGroup === 'desktop') {
            $includeInSlotGroup = $itemLocation === 'header'
                || ($itemType === 'link' && $itemLocation === 'footer');
        } elseif ($slotGroup === 'mobile') {
            $includeInSlotGroup = $itemType === 'link' && $itemLocation === 'mobile';
        } elseif ($slotGroup === 'mobile_more') {
            $includeInSlotGroup = $itemType === 'link' && $itemLocation === 'mobile_more' && $itemSection === 'more';
        }

        if (!$includeInSlotGroup) {
            continue;
        }

        $feature_key = (string) ($item['feature_key'] ?? '');
        $feature_note = '';
        if ($feature_key !== '') {
            $feature = $featureFlagsByKey[$feature_key] ?? [];
            if (empty($feature['is_visible'])) {
                $feature_note = ' - hidden by Feature Access';
            } elseif (empty($feature['is_accessible'])) {
                $feature_note = ' - opens fallback';
            }
        }

        $audience = (string) ($item['audience'] ?? 'all');
        if ($audience !== 'all' && $audience !== $targetAudience) {
            continue;
        }
        $audience_label = $audience === 'member' ? 'signed-in' : ($audience === 'guest' ? 'guest' : 'all');
        $type_label = $itemType === 'dropdown' ? ' [Dropdown]' : '';
        $options[] = [
            'key' => (string) $key,
            'label' => (string) ($item['label'] ?? 'Navigation item') . $type_label . ' [' . (string) ($item['section_label'] ?? ($itemLocation . ' / ' . $itemSection)) . '] (' . $audience_label . ')' . $feature_note,
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']),
        ];
    }

    usort($options, static function (array $a, array $b): int {
        $enabledCompare = (int) $b['is_enabled'] <=> (int) $a['is_enabled'];
        if ($enabledCompare !== 0) {
            return $enabledCompare;
        }

        $sortCompare = (int) $a['sort_order'] <=> (int) $b['sort_order'];
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp((string) $a['label'], (string) $b['label']);
    });

    return $options;
};
$navigation_slot_values = static function (PDO $db, array $registry, string $slotGroup, string $location, string $sectionKey, string $targetAudience, int $limit): array {
    $values = [];
    try {
        $stmt = $db->prepare("SELECT slot_number, nav_key FROM navigation_slots WHERE slot_group = ? ORDER BY slot_number ASC");
        $stmt->execute([$slotGroup]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $slot_number = (int) ($row['slot_number'] ?? 0);
            if ($slot_number >= 1 && $slot_number <= $limit) {
                $values[$slot_number] = (string) ($row['nav_key'] ?? '');
            }
        }
    } catch (Throwable $e) {
        $values = [];
    }

    if ($values) {
        for ($slot = 1; $slot <= $limit; $slot++) {
            $values[$slot] = (string) ($values[$slot] ?? '');
        }

        return $values;
    }

    $items = [];
    foreach ($registry as $key => $item) {
        if ((string) ($item['location'] ?? '') !== $location) {
            continue;
        }
        if ((string) ($item['section_key'] ?? '') !== $sectionKey) {
            continue;
        }
        if (empty($item['is_enabled'])) {
            continue;
        }
        $audience = (string) ($item['audience'] ?? 'all');
        if ($audience !== 'all' && $audience !== $targetAudience) {
            continue;
        }
        $items[] = [
            'key' => (string) $key,
            'sort_order' => (int) ($item['sort_order'] ?? 0),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        $sortCompare = (int) $a['sort_order'] <=> (int) $b['sort_order'];
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp((string) $a['key'], (string) $b['key']);
    });

    $values = [];
    for ($slot = 1; $slot <= $limit; $slot++) {
        $values[$slot] = (string) ($items[$slot - 1]['key'] ?? '');
    }

    return $values;
};
$desktop_guest_slot_options = $navigation_slot_options($navigation_registry, 'desktop', 'guest', $feature_flags_by_key);
$desktop_member_slot_options = $navigation_slot_options($navigation_registry, 'desktop', 'member', $feature_flags_by_key);
$mobile_guest_slot_options = $navigation_slot_options($navigation_registry, 'mobile', 'guest', $feature_flags_by_key);
$mobile_member_slot_options = $navigation_slot_options($navigation_registry, 'mobile', 'member', $feature_flags_by_key);
$desktop_guest_slot_values = $navigation_slot_values($db, $navigation_registry, 'desktop_guest', 'header', 'primary', 'guest', 6);
$desktop_member_slot_values = $navigation_slot_values($db, $navigation_registry, 'desktop_member', 'header', 'primary', 'member', 6);
$mobile_guest_slot_values = $navigation_slot_values($db, $navigation_registry, 'mobile_guest', 'mobile', 'bottom', 'guest', 5);
$mobile_member_slot_values = $navigation_slot_values($db, $navigation_registry, 'mobile_member', 'mobile', 'bottom', 'member', 5);
?>

<style>
.launch-wrap { display: grid; gap: 18px; padding: 0 20px 22px; }
.launch-tabs { display: flex; gap: 12px; flex-wrap: wrap; }
.launch-tab {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 14px;
    border: 1px solid rgba(148,163,184,.14);
    background: rgba(15,23,42,.58);
    color: #cbd5e1;
    font-weight: 800;
    text-decoration: none;
    transition: border-color .18s ease, transform .18s ease, background .18s ease, color .18s ease;
}
.launch-tab:hover { border-color: rgba(96,165,250,.32); color: #f8fafc; transform: translateY(-1px); }
.launch-tab.is-active { background: linear-gradient(135deg, rgba(29,78,216,.88), rgba(30,64,175,.82)); border-color: rgba(96,165,250,.48); color: #fff; box-shadow: 0 12px 28px rgba(29,78,216,.22); }
.launch-tab small { display: block; color: rgba(226,232,240,.72); font-size: 11px; font-weight: 700; }
.launch-tab.is-active small { color: rgba(255,255,255,.82); }
.launch-panel { display: grid; gap: 18px; }
.launch-control-hero {
    position: relative;
    overflow: hidden;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 20px;
    align-items: center;
    padding: 24px;
    border: 1px solid rgba(148,163,184,.14);
    border-radius: 20px;
    background:
        radial-gradient(circle at 0% 0%, rgba(212,175,55,.12), transparent 34%),
        linear-gradient(135deg, rgba(15,23,42,.88), rgba(30,41,59,.52));
    box-shadow: 0 22px 60px rgba(2,6,23,.24);
}
.launch-control-hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -120px; top: -150px; border-radius: 999px; background: rgba(29,78,216,.18); filter: blur(12px); pointer-events: none; }
.launch-kicker { display: inline-flex; align-items: center; gap: 8px; width: fit-content; padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(212,175,55,.28); background: rgba(212,175,55,.10); color: #fde68a; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.launch-control-hero h2 { margin: 12px 0 8px; color: #f8fafc; font-size: clamp(1.45rem, 3vw, 2rem); }
.launch-control-hero p { margin: 0; max-width: 780px; color: #b9c7e8; line-height: 1.65; }
.launch-hero-side { position: relative; z-index: 1; display: grid; gap: 8px; min-width: 210px; }
.launch-mode-card { border: 1px solid rgba(34,197,94,.2); border-radius: 16px; padding: 14px; background: rgba(34,197,94,.08); }
.launch-mode-card strong { display: block; color: #f8fafc; font-size: 22px; line-height: 1; }
.launch-mode-card span { display: block; margin-top: 6px; color: #86efac; font-size: 12px; font-weight: 800; }
.launch-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.launch-metric { border: 1px solid rgba(148,163,184,.12); border-radius: 16px; padding: 16px; background: rgba(15,23,42,.62); }
.launch-metric span { display: block; color: #94a3b8; font-size: 12px; font-weight: 800; text-transform: uppercase; }
.launch-metric strong { display: block; margin-top: 8px; color: #f8fafc; font-size: 28px; line-height: 1; }
.launch-metric.is-live strong { color: #86efac; }
.launch-metric.is-fallback strong { color: #fbbf24; }
.launch-metric.is-off strong { color: #fca5a5; }
.launch-group { border: 1px solid rgba(148,163,184,.12); border-radius: 18px; overflow: hidden; background: rgba(15,23,42,.58); box-shadow: 0 16px 42px rgba(2,6,23,.18); }
.launch-group-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid rgba(148,163,184,.12); background: rgba(2,6,23,.22); }
.launch-group-head h3 { margin: 0; color: #f8fafc; display: inline-flex; align-items: center; gap: 9px; }
.launch-group-head h3 i { color: #facc15; }
.launch-group-count { color: #94a3b8; font-size: 12px; font-weight: 800; }
.launch-row { display: grid; grid-template-columns: minmax(210px, .68fr) minmax(190px, .44fr) minmax(300px, 1fr); gap: 16px; padding: 18px; border-bottom: 1px solid rgba(148,163,184,.09); }
.launch-row:last-child { border-bottom: 0; }
.launch-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.launch-title strong { color: #f8fafc; font-size: 15px; }
.launch-key { display: block; margin-top: 6px; color: #64748b; font-size: 12px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; }
.launch-hint { display: block; margin-top: 10px; color: #94a3b8; font-size: 12px; line-height: 1.5; }
.launch-toggles { display: grid; gap: 10px; align-content: start; }
.launch-toggle {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
    padding: 10px;
    border: 1px solid rgba(148,163,184,.12);
    border-radius: 14px;
    background: rgba(2,6,23,.22);
    color: #cbd5e1;
    font-weight: 800;
}
.launch-switch { position: relative; display: inline-flex; width: 42px; height: 24px; }
.launch-switch input { opacity: 0; width: 0; height: 0; }
.launch-slider { position: absolute; inset: 0; border-radius: 999px; background: rgba(100,116,139,.5); transition: .18s ease; }
.launch-slider::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; border-radius: 50%; background: #e2e8f0; transition: .18s ease; }
.launch-switch input:checked + .launch-slider { background: #1d4ed8; box-shadow: 0 0 0 3px rgba(29,78,216,.16); }
.launch-switch input:checked + .launch-slider::before { transform: translateX(18px); background: #fff; }
.launch-toggle-copy { display: grid; gap: 2px; min-width: 0; }
.launch-toggle-copy small { color: #64748b; font-size: 11px; font-weight: 700; }
.launch-fields { display: grid; gap: 10px; }
.launch-fields label, .launch-nav-fields label, .launch-create-card label { display: grid; gap: 6px; color: #94a3b8; font-size: 12px; font-weight: 700; }
.launch-fields input, .launch-fields textarea, .launch-fields select, .launch-nav-fields input, .launch-nav-fields select, .launch-create-card input, .launch-create-card select {
    width: 100%;
    border: 1px solid rgba(148,163,184,.18);
    border-radius: 12px;
    background: rgba(2,6,23,.38);
    color: #e2e8f0;
    padding: 10px 12px;
    font: inherit;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
.launch-fields input:focus, .launch-fields textarea:focus, .launch-fields select:focus, .launch-nav-fields input:focus, .launch-nav-fields select:focus, .launch-create-card input:focus, .launch-create-card select:focus {
    outline: none;
    border-color: rgba(96,165,250,.58);
    box-shadow: 0 0 0 3px rgba(29,78,216,.14);
    background: rgba(2,6,23,.52);
}
.launch-fields textarea { min-height: 84px; resize: vertical; }
.launch-fields select, .launch-nav-fields select, .launch-create-card select {
    background-image:
        linear-gradient(45deg, transparent 50%, #93c5fd 50%),
        linear-gradient(135deg, #93c5fd 50%, transparent 50%);
    background-position:
        calc(100% - 20px) calc(50% - 4px),
        calc(100% - 14px) calc(50% - 4px);
    background-size: 6px 6px, 6px 6px;
    background-repeat: no-repeat;
    padding-right: 34px;
    color-scheme: dark;
}
.launch-field-grid { display: grid; grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr); gap: 10px; }
.launch-status { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.launch-status.is-live { background: rgba(34,197,94,.14); color: #86efac; }
.launch-status.is-hidden { background: rgba(59,130,246,.14); color: #93c5fd; }
.launch-status.is-fallback { background: rgba(245,158,11,.16); color: #fbbf24; }
.launch-status.is-off { background: rgba(239,68,68,.14); color: #fca5a5; }
.launch-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.launch-save-bar { position: sticky; bottom: 14px; z-index: 8; margin-top: 4px; padding: 12px; border: 1px solid rgba(148,163,184,.16); border-radius: 16px; background: rgba(15,23,42,.92); backdrop-filter: blur(12px); box-shadow: 0 18px 50px rgba(2,6,23,.34); }
.launch-defaults { margin-left: auto; display: inline-flex; gap: 10px; align-items: center; color: #94a3b8; font-size: 13px; }
.launch-message { padding: 12px 14px; border-radius: 12px; font-weight: 700; }
.launch-message.success { background: rgba(34,197,94,.10); color: #86efac; border: 1px solid rgba(34,197,94,.18); }
.launch-message.error { background: rgba(239,68,68,.10); color: #fca5a5; border: 1px solid rgba(239,68,68,.18); }
.launch-warning { display: flex; gap: 10px; align-items: flex-start; border: 1px solid rgba(245,158,11,.18); border-radius: 16px; padding: 14px; background: rgba(245,158,11,.08); color: #fde68a; }
.launch-warning i { margin-top: 2px; }
.launch-warning p { margin: 0; color: #f8e7b0; line-height: 1.55; font-size: 13px; }
.launch-section-gap { margin-top: 6px; }
.launch-nav-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.launch-nav-card { border: 1px solid rgba(148,163,184,.12); border-radius: 16px; padding: 16px; background: rgba(15,23,42,.62); }
.launch-nav-card span { display: block; color: #94a3b8; font-size: 12px; font-weight: 800; text-transform: uppercase; }
.launch-nav-card strong { display: block; margin-top: 8px; color: #f8fafc; font-size: 28px; line-height: 1; }
.launch-nav-card.is-enabled strong { color: #86efac; }
.launch-nav-card.is-disabled strong { color: #fca5a5; }
.launch-nav-card.is-feature-blocked strong { color: #fbbf24; }
.launch-helper-grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); gap: 14px; }
.launch-help-card { border: 1px solid rgba(148,163,184,.12); border-radius: 18px; padding: 16px; background: rgba(15,23,42,.58); box-shadow: 0 16px 42px rgba(2,6,23,.16); }
.launch-help-card h3 { margin: 0 0 8px; color: #f8fafc; display: flex; gap: 8px; align-items: center; }
.launch-help-card p { margin: 0; color: #94a3b8; line-height: 1.6; font-size: 13px; }
.launch-simple-list { display: grid; gap: 8px; margin: 12px 0 0; padding: 0; list-style: none; }
.launch-simple-list li { display: flex; gap: 8px; align-items: flex-start; color: #cbd5e1; font-size: 13px; line-height: 1.45; }
.launch-simple-list i { color: #facc15; margin-top: 2px; }
.launch-phone-preview { display: grid; gap: 12px; }
.launch-phone-preview h4 { margin: 0; color: #e2e8f0; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
.launch-phone-bar { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; padding: 10px; border: 1px solid rgba(148,163,184,.14); border-radius: 16px; background: rgba(2,6,23,.34); }
.launch-phone-slot { min-height: 72px; display: grid; place-items: center; gap: 5px; border-radius: 12px; border: 1px solid rgba(148,163,184,.12); background: rgba(15,23,42,.58); color: #cbd5e1; text-align: center; padding: 8px 4px; }
.launch-phone-slot i { color: #facc15; font-size: 16px; }
.launch-phone-slot span { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 800; }
.launch-phone-slot.is-empty { color: #64748b; border-style: dashed; }
.launch-phone-slot.is-empty i { color: #475569; }
.launch-mobile-note { margin-top: 10px; color: #94a3b8; font-size: 12px; line-height: 1.5; }
.launch-slot-card { border: 1px solid rgba(34,197,94,.18); border-radius: 18px; padding: 18px; background: linear-gradient(135deg, rgba(15,23,42,.72), rgba(6,78,59,.18)); box-shadow: 0 16px 42px rgba(2,6,23,.18); }
.launch-slot-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; margin-bottom: 16px; }
.launch-slot-head h3 { margin: 0 0 6px; color: #f8fafc; display: inline-flex; align-items: center; gap: 9px; }
.launch-slot-head p { margin: 0; color: #a7f3d0; line-height: 1.55; font-size: 13px; }
.launch-slot-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.launch-slot-group { border: 1px solid rgba(148,163,184,.12); border-radius: 16px; padding: 14px; background: rgba(2,6,23,.26); }
.launch-slot-group h4 { margin: 0 0 10px; color: #e2e8f0; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
.launch-slot-grid { display: grid; gap: 10px; }
.launch-slot-grid label { display: grid; gap: 6px; color: #94a3b8; font-size: 12px; font-weight: 800; }
.launch-slot-grid select {
    width: 100%;
    border: 1px solid rgba(148,163,184,.18);
    border-radius: 12px;
    background: rgba(2,6,23,.44);
    color: #e2e8f0;
    padding: 10px 12px;
    font: inherit;
    color-scheme: dark;
}
.launch-nav-row { display: grid; grid-template-columns: minmax(220px, .7fr) minmax(320px, 1fr) minmax(240px, .7fr); gap: 16px; padding: 18px; border-bottom: 1px solid rgba(148,163,184,.09); }
.launch-nav-row.is-mobile-item { background: linear-gradient(90deg, rgba(59,130,246,.08), rgba(15,23,42,0)); }
.launch-nav-row:last-child { border-bottom: 0; }
.launch-nav-meta { display: grid; gap: 8px; align-content: start; }
.launch-nav-status { display: inline-flex; width: fit-content; align-items: center; gap: 8px; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.launch-nav-status.is-enabled { background: rgba(34,197,94,.14); color: #86efac; }
.launch-nav-status.is-disabled { background: rgba(239,68,68,.14); color: #fca5a5; }
.launch-pill-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.launch-nav-origin { display: inline-flex; width: fit-content; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 900; text-transform: uppercase; }
.launch-nav-origin.is-system { background: rgba(59,130,246,.14); color: #93c5fd; }
.launch-nav-origin.is-custom { background: rgba(245,158,11,.16); color: #fbbf24; }
.launch-nav-origin.is-mobile { background: rgba(14,165,233,.14); color: #7dd3fc; }
.launch-nav-origin.is-feature-live { background: rgba(34,197,94,.14); color: #86efac; }
.launch-nav-origin.is-feature-hidden { background: rgba(245,158,11,.16); color: #fbbf24; }
.launch-nav-origin.is-feature-off { background: rgba(239,68,68,.14); color: #fca5a5; }
.launch-nav-meta small { color: #64748b; line-height: 1.5; }
.launch-nav-fields { display: grid; gap: 10px; }
.launch-nav-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.launch-nav-grid-3 { display: grid; grid-template-columns: 110px minmax(0, 1fr) 130px; gap: 10px; }
.launch-create-card { border: 1px solid rgba(148,163,184,.12); border-radius: 18px; padding: 18px; background: rgba(15,23,42,.58); box-shadow: 0 16px 42px rgba(2,6,23,.18); }
.launch-create-card h3 { margin: 0 0 8px; color: #f8fafc; }
.launch-create-card p { margin: 0 0 14px; color: #94a3b8; line-height: 1.6; }
.launch-create-form { display: grid; gap: 10px; }
.launch-create-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.launch-delete-btn { width: fit-content; }
.launch-tab-panel[hidden] { display: none; }
@media (max-width: 1180px) { .launch-row { grid-template-columns: 1fr; } .launch-field-grid { grid-template-columns: 1fr; } }
@media (max-width: 1180px) { .launch-nav-row, .launch-nav-grid-2, .launch-nav-grid-3, .launch-helper-grid, .launch-slot-groups { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .launch-wrap { padding: 0 14px 18px; } .launch-control-hero { grid-template-columns: 1fr; padding: 20px; } .launch-metrics, .launch-nav-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .launch-defaults { margin-left: 0; } .launch-tabs { display: grid; grid-template-columns: 1fr; } .launch-group-head, .launch-title, .launch-create-actions, .launch-actions, .launch-slot-head { flex-direction: column; align-items: stretch; } .launch-group-count, .launch-delete-btn { width: 100%; } .launch-delete-btn, .launch-create-actions .btn, .launch-actions .btn { justify-content: center; } .launch-mode-card { min-width: 0; } .launch-phone-bar { gap: 6px; padding: 8px; } .launch-phone-slot { min-height: 64px; } }
@media (max-width: 520px) { .launch-metrics, .launch-nav-summary { grid-template-columns: 1fr; } .launch-row, .launch-nav-row { padding: 14px; } .launch-save-bar .btn, .launch-create-actions .btn, .launch-delete-btn { width: 100%; justify-content: center; } .launch-save-bar { position: static; } .launch-control-hero { padding: 16px; } .launch-create-card, .launch-group { border-radius: 16px; } }
</style>

<div class="launch-wrap">
<?php if ($success_message !== ''): ?>
    <div data-toast data-toast-message="<?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="success" style="display:none;"></div>
<?php endif; ?>
<?php if ($error_message !== ''): ?>
    <div data-toast data-toast-message="<?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="error" style="display:none;"></div>
<?php endif; ?>

<section class="launch-control-hero">
    <div>
        <span class="launch-kicker"><i class="fas fa-rocket"></i> MVP Gatekeeper</span>
        <h2>Launch & Navigation Control Center</h2>
        <p>Feature gating and shared navigation are centralized here so the launch team can manage changes without editing templates every time.</p>
    </div>
    <div class="launch-hero-side">
        <div class="launch-mode-card">
            <strong><?php echo (int) $summary['live']; ?>/<?php echo (int) $summary['total']; ?></strong>
            <span>features live right now</span>
        </div>
        <div class="launch-mode-card">
            <strong><?php echo (int) $navigation_summary['enabled']; ?>/<?php echo (int) $navigation_summary['total']; ?></strong>
            <span>nav items enabled</span>
        </div>
    </div>
</section>
<nav class="launch-tabs" aria-label="Launch control tabs">
    <a href="<?php echo htmlspecialchars(ADMIN_BASE_URL . '/launch-control.php?tab=features', ENT_QUOTES, 'UTF-8'); ?>" class="launch-tab <?php echo $current_tab === 'features' ? 'is-active' : ''; ?>">
        <i class="fas fa-rocket"></i>
        <span>Feature Access<small>Visibility, access, fallbacks</small></span>
    </a>
    <a href="<?php echo htmlspecialchars(ADMIN_BASE_URL . '/launch-control.php?tab=navigation', ENT_QUOTES, 'UTF-8'); ?>" class="launch-tab <?php echo $current_tab === 'navigation' ? 'is-active' : ''; ?>">
        <i class="fas fa-compass-drafting"></i>
        <span>Navigation Controller<small>Header, footer, mobile nav</small></span>
    </a>
</nav>

<section class="launch-tab-panel" <?php echo $current_tab === 'features' ? '' : 'hidden'; ?>>
    <div class="launch-panel">
        <section class="launch-metrics" aria-label="Launch control summary">
            <div class="launch-metric is-live"><span>Live</span><strong><?php echo (int) $summary['live']; ?></strong></div>
            <div class="launch-metric"><span>Hidden</span><strong><?php echo (int) $summary['hidden']; ?></strong></div>
            <div class="launch-metric is-fallback"><span>Fallback</span><strong><?php echo (int) $summary['fallback']; ?></strong></div>
            <div class="launch-metric is-off"><span>Fully Off</span><strong><?php echo (int) $summary['off']; ?></strong></div>
        </section>

        <section class="launch-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <p><strong>Quick rule:</strong> visibility controls links and buttons; direct access controls the URL itself. Keep admin access outside this system so launch controls can never lock you out.</p>
        </section>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save_flags">
            <input type="hidden" name="current_tab" value="features">

            <?php foreach ($grouped_flags as $group => $items): ?>
                <section class="launch-group">
                    <div class="launch-group-head">
                        <h3><i class="fas fa-layer-group"></i><?php echo htmlspecialchars((string) $group, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="launch-group-count"><?php echo count($items); ?> controls</span>
                    </div>
                    <?php foreach ($items as $flag): ?>
                        <?php $meta = $status_meta($flag); $key = (string) $flag['feature_key']; ?>
                        <div class="launch-row">
                            <div>
                                <input type="hidden" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][label]" value="<?php echo htmlspecialchars((string) $flag['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="launch-title">
                                    <strong><?php echo htmlspecialchars((string) $flag['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="launch-status <?php echo htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="launch-key"><?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="launch-hint"><?php echo htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="launch-toggles">
                                <label class="launch-toggle">
                                    <span class="launch-switch">
                                        <input type="checkbox" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][is_visible]" value="1" <?php echo !empty($flag['is_visible']) ? 'checked' : ''; ?>>
                                        <span class="launch-slider"></span>
                                    </span>
                                    <span class="launch-toggle-copy">
                                        <span>Visible in UI</span>
                                        <small>Header, footer, dashboard buttons</small>
                                    </span>
                                </label>
                                <label class="launch-toggle">
                                    <span class="launch-switch">
                                        <input type="checkbox" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][is_accessible]" value="1" <?php echo !empty($flag['is_accessible']) ? 'checked' : ''; ?>>
                                        <span class="launch-slider"></span>
                                    </span>
                                    <span class="launch-toggle-copy">
                                        <span>Direct access</span>
                                        <small>Blocks or allows typed URLs</small>
                                    </span>
                                </label>
                            </div>
                            <div class="launch-fields">
                                <label>
                                    Fallback title
                                    <input type="text" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][fallback_title]" value="<?php echo htmlspecialchars((string) $flag['fallback_title'], ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                                <label>
                                    Fallback message
                                    <textarea name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][fallback_message]"><?php echo htmlspecialchars((string) ($flag['fallback_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </label>
                                <div class="launch-field-grid">
                                    <label>
                                        CTA label
                                        <input type="text" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][fallback_cta_label]" value="<?php echo htmlspecialchars((string) $flag['fallback_cta_label'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                    <label>
                                        CTA URL
                                        <input type="text" name="features[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>][fallback_cta_url]" value="<?php echo htmlspecialchars((string) $flag['fallback_cta_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <div class="launch-actions launch-save-bar">
                <button type="submit" class="btn btn-primary">Save Launch Controls</button>
                <span class="launch-defaults">Changes apply immediately after save.</span>
            </div>
        </form>

        <form method="POST" action="" class="launch-actions">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="restore_defaults">
            <input type="hidden" name="current_tab" value="features">
            <button type="submit" class="btn btn-secondary">Restore MVP Defaults</button>
            <label class="launch-defaults">
                <input type="checkbox" name="reset_messages" value="1">
                <span>Reset custom fallback messages too</span>
            </label>
        </form>
    </div>
</section>

<section class="launch-tab-panel" <?php echo $current_tab === 'navigation' ? '' : 'hidden'; ?>>
    <div class="launch-panel">
        <?php
        $level_labels = ['visitor' => 'Visitor', 'beginner' => 'Beginner', 'pro' => 'Pro', 'expert' => 'Expert'];
        $level_icons = ['visitor' => 'fas fa-eye', 'beginner' => 'fas fa-seedling', 'pro' => 'fas fa-fire', 'expert' => 'fas fa-crown'];
        $level_audience_map = ['visitor' => 'guest', 'beginner' => 'member', 'pro' => 'member', 'expert' => 'member'];

        $filtered_level = $current_level;
        $filtered_audience = $level_audience_map[$filtered_level] ?? 'guest';

        $desktop_slot_group = 'desktop_' . $filtered_level;
        $mobile_slot_group = 'mobile_' . $filtered_level;

        $desktop_options = $navigation_slot_options($navigation_registry, 'desktop', $filtered_audience, $feature_flags_by_key);
        $mobile_options = $navigation_slot_options($navigation_registry, 'mobile', $filtered_audience, $feature_flags_by_key);
        $mobile_more_options = $navigation_slot_options($navigation_registry, 'mobile_more', $filtered_audience, $feature_flags_by_key);

        $desktop_location = $filtered_level === 'visitor' ? 'header' : 'header';
        $desktop_section = 'primary';
        $mobile_location = 'mobile';
        $mobile_section = 'bottom';

        $desktop_values = $navigation_slot_values($db, $navigation_registry, $desktop_slot_group, $desktop_location, $desktop_section, $filtered_audience, 6);
        $mobile_values = $navigation_slot_values($db, $navigation_registry, $mobile_slot_group, $mobile_location, $mobile_section, $filtered_audience, 4);
        $mobile_more_values = $navigation_slot_values($db, $navigation_registry, 'mobile_more_' . $filtered_level, 'mobile_more', 'more', $filtered_audience, 4);
        ?>

        <nav class="launch-tabs" style="margin-bottom: 4px;" aria-label="Level filter">
            <?php foreach ($allowed_levels as $lvl): ?>
                <a href="<?php echo htmlspecialchars(ADMIN_BASE_URL . '/launch-control.php?tab=navigation&level=' . $lvl, ENT_QUOTES, 'UTF-8'); ?>"
                   class="launch-tab <?php echo $current_level === $lvl ? 'is-active' : ''; ?>">
                    <i class="<?php echo $level_icons[$lvl]; ?>"></i>
                    <span><?php echo $level_labels[$lvl]; ?><small><?php echo $lvl === 'visitor' ? 'Unauthenticated' : 'Signed-in'; ?></small></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="POST" action="" class="launch-slot-card">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save_navigation_slots">
            <input type="hidden" name="current_tab" value="navigation">
            <input type="hidden" name="current_level" value="<?php echo htmlspecialchars($current_level, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="launch-slot-head">
                <div>
                    <h3><i class="fas fa-table-cells-large"></i> <?php echo $level_labels[$current_level]; ?> Navigation Display</h3>
                    <p>Configure the Desktop Nav (6 slots) and Mobile Bottom Navigation (5 slots) for <?php echo $level_labels[$current_level]; ?> users.</p>
                </div>
                <button type="submit" class="btn btn-primary">Save <?php echo $level_labels[$current_level]; ?> Slots</button>
            </div>
            <div class="launch-slot-groups">
                <div class="launch-slot-group">
                    <h4><i class="fas fa-desktop"></i> Desktop Nav (<?php echo $level_labels[$current_level]; ?>)</h4>
                    <div class="launch-slot-grid">
                        <?php for ($slot = 1; $slot <= 6; $slot++): ?>
                            <label>
                                Slot <?php echo (int) $slot; ?>
                                <select name="desktop_slots[<?php echo (int) $slot; ?>]">
                                    <option value="">Empty slot</option>
                                    <?php foreach ($desktop_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars((string) $option['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) ($desktop_values[$slot] ?? '') === (string) $option['key'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="launch-slot-group">
                    <h4><i class="fas fa-mobile-screen-button"></i> Mobile Bottom Nav (<?php echo $level_labels[$current_level]; ?>)</h4>
                    <div class="launch-slot-grid">
                        <?php for ($slot = 1; $slot <= 4; $slot++): ?>
                            <label>
                                Slot <?php echo (int) $slot; ?>
                                <select name="mobile_slots[<?php echo (int) $slot; ?>]">
                                    <option value="">Empty slot</option>
                                    <?php foreach ($mobile_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars((string) $option['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) ($mobile_values[$slot] ?? '') === (string) $option['key'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="launch-slot-group">
                    <h4><i class="fas fa-ellipsis-h"></i> Mobile More Menu (<?php echo $level_labels[$current_level]; ?>)</h4>
                    <div class="launch-slot-grid">
                        <?php for ($slot = 1; $slot <= 4; $slot++): ?>
                            <label>
                                Slot <?php echo (int) $slot; ?>
                                <select name="mobile_more_slots[<?php echo (int) $slot; ?>]">
                                    <option value="">Empty slot</option>
                                    <?php foreach ($mobile_more_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars((string) $option['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) ($mobile_more_values[$slot] ?? '') === (string) $option['key'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <p class="launch-mobile-note">Feature Access still controls whether a selected page is live during MVP launch. The center More button is reserved; configure the four direct links and four More links above.</p>
        </form>

        <?php
        // Collect all unique section keys from the registry for dynamic dropdown options.
        // This includes both `section_key` values AND `children_section_key` values from dropdown items,
        // so newly created dropdown sections appear in the Section dropdown for adding child links.
        $custom_section_options = [];

        $addSectionOption = static function (array &$map, string $location, string $sectionKey, string $label) {
            if ($location === '' || $sectionKey === '') {
                return;
            }
            $compound = $location . ':' . $sectionKey;
            if (isset($map[$compound])) {
                return;
            }
            $map[$compound] = [
                'location' => $location,
                'section_key' => $sectionKey,
                'label' => $label !== '' ? $label : coinrexNavigationSectionLabel($location, $sectionKey),
            ];
        };

        foreach ($navigation_registry as $nav_item) {
            $item_location = (string) ($nav_item['location'] ?? '');
            $item_section = (string) ($nav_item['section_key'] ?? '');
            $item_children = (string) ($nav_item['children_section_key'] ?? '');
            $item_label = (string) ($nav_item['section_label'] ?? '');

            // Add the item's own section
            $addSectionOption($custom_section_options, $item_location, $item_section, $item_label);

            // If this is a dropdown, also add its children section so child links can be added to it
            if ((string) ($nav_item['item_type'] ?? 'link') === 'dropdown' && $item_children !== '') {
                $addSectionOption($custom_section_options, $item_location, $item_children, coinrexNavigationSectionLabel($item_location, $item_children));
            }
        }
        ksort($custom_section_options);
        ?>

        <?php
        // Collect dropdown items and available link items for the "Add Existing Link to Dropdown" form
        $dropdown_items = [];
        $available_link_items = [];
        foreach ($navigation_registry as $nav_key => $nav_item) {
            $item_type = (string) ($nav_item['item_type'] ?? 'link');
            $item_location = (string) ($nav_item['location'] ?? '');
            $item_section = (string) ($nav_item['section_key'] ?? '');
            $item_children = (string) ($nav_item['children_section_key'] ?? '');

            if ($item_type === 'dropdown') {
                $dropdown_items[$nav_key] = [
                    'key' => $nav_key,
                    'label' => (string) ($nav_item['label'] ?? $nav_key),
                    'location' => $item_location,
                    'children_section' => $item_children,
                ];
            } elseif ($item_type === 'link') {
                // Only show links that are NOT already children of a dropdown section
                $is_dropdown_child = false;
                foreach ($navigation_registry as $check_item) {
                    if ((string) ($check_item['item_type'] ?? 'link') === 'dropdown'
                        && (string) ($check_item['children_section_key'] ?? '') === $item_section) {
                        $is_dropdown_child = true;
                        break;
                    }
                }
                if (!$is_dropdown_child) {
                    $available_link_items[$nav_key] = [
                        'key' => $nav_key,
                        'label' => (string) ($nav_item['label'] ?? $nav_key),
                        'location' => $item_location,
                        'section' => $item_section,
                    ];
                }
            }
        }
        ?>

        <section class="launch-create-card">
            <h3><i class="fas fa-plus-circle"></i> Add Existing Link to Dropdown</h3>
            <p>Select an existing link and a dropdown menu to add the link as a child of that dropdown.</p>
            <form method="POST" action="" class="launch-create-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="assign_navigation_child">
                <input type="hidden" name="current_tab" value="navigation">
                <div class="launch-nav-grid-2">
                    <label>
                        Dropdown Menu
                        <select name="assign_child[dropdown_key]" required>
                            <option value="">-- Select dropdown --</option>
                            <?php foreach ($dropdown_items as $dropdown): ?>
                                <option value="<?php echo htmlspecialchars((string) $dropdown['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $dropdown['label'] . ' [' . ucwords((string) $dropdown['location']) . ']', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Existing Link
                        <select name="assign_child[link_key]" required>
                            <option value="">-- Select link --</option>
                            <?php foreach ($available_link_items as $link): ?>
                                <option value="<?php echo htmlspecialchars((string) $link['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $link['label'] . ' [' . ucwords((string) $link['location']) . ']', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="launch-create-actions" style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary">Add to Dropdown</button>
                    <span class="launch-defaults">Tip: the link will move into the dropdown's children section.</span>
                </div>
            </form>
        </section>

        <?php
        // Collect all navigation items for the edit form
        $edit_nav_items = [];
        foreach ($navigation_registry as $nav_key => $nav_item) {
            $edit_nav_items[$nav_key] = [
                'key' => $nav_key,
                'label' => (string) ($nav_item['label'] ?? $nav_key),
                'location' => (string) ($nav_item['location'] ?? ''),
                'item_type' => (string) ($nav_item['item_type'] ?? 'link'),
                'custom_url' => (string) ($nav_item['custom_url'] ?? ''),
                'icon_class' => (string) ($nav_item['icon_class'] ?? ''),
                'badge_text' => (string) ($nav_item['badge_text'] ?? ''),
                'sort_order' => (int) ($nav_item['sort_order'] ?? 0),
                'is_enabled' => !empty($nav_item['is_enabled']),
            ];
        }
        ?>

        <section class="launch-create-card">
            <h3><i class="fas fa-pen-to-square"></i> Edit Navigation Item</h3>
            <p>Select a navigation item to edit its label, URL, icon, badge, and visibility.</p>
            <form method="POST" action="" class="launch-create-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="edit_navigation_item">
                <input type="hidden" name="current_tab" value="navigation">
                <div class="launch-nav-grid-2">
                    <label>
                        Navigation Item
                        <select name="edit_nav[nav_key]" id="editNavItemSelect" required>
                            <option value="">-- Select item to edit --</option>
                            <?php foreach ($edit_nav_items as $item): ?>
                                <option value="<?php echo htmlspecialchars((string) $item['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-label="<?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-url="<?php echo htmlspecialchars((string) $item['custom_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-icon="<?php echo htmlspecialchars((string) $item['icon_class'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-badge="<?php echo htmlspecialchars((string) $item['badge_text'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-order="<?php echo (int) $item['sort_order']; ?>"
                                    data-enabled="<?php echo $item['is_enabled'] ? '1' : '0'; ?>"
                                    data-type="<?php echo htmlspecialchars((string) $item['item_type'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $item['label'] . ' [' . ucwords((string) $item['location']) . ']' . ($item['item_type'] === 'dropdown' ? ' [Dropdown]' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Label
                        <input type="text" name="edit_nav[label]" id="editNavLabel" required>
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;">
                    <label>
                        Custom URL
                        <input type="text" name="edit_nav[custom_url]" id="editNavUrl" placeholder="https://... or /public/page.php">
                    </label>
                    <label>
                        Icon class
                        <input type="text" name="edit_nav[icon_class]" id="editNavIcon" placeholder="fas fa-link">
                    </label>
                    <label>
                        Badge
                        <input type="text" name="edit_nav[badge_text]" id="editNavBadge" placeholder="NEW">
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;">
                    <label>
                        Sort Order
                        <input type="number" name="edit_nav[sort_order]" id="editNavOrder" value="100">
                    </label>
                    <label>
                        <span style="display:block; margin-bottom:6px;">Enabled</span>
                        <select name="edit_nav[is_enabled]" id="editNavEnabled">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </label>
                </div>
                <div class="launch-create-actions" style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <span class="launch-defaults">Tip: dropdowns don't need a URL.</span>
                </div>
            </form>
        </section>

        <section class="launch-create-card">
            <h3><i class="fas fa-plus-circle"></i> Add New Link/Page or Dropdown</h3>
            <p>Add a page, custom URL, or dropdown menu, then select it in the slot dropdown above.</p>
            <form method="POST" action="" class="launch-create-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="create_navigation_item">
                <input type="hidden" name="current_tab" value="navigation">
                <div class="launch-nav-grid-2">
                    <label>
                        Label
                        <input type="text" name="new_nav[label]" placeholder="New link label" required>
                    </label>
                    <label>
                        Custom URL
                        <input type="text" name="new_nav[custom_url]" placeholder="https://... or /public/page.php">
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;">
                    <label>
                        Type
                        <select name="new_nav[item_type]" id="newNavItemType">
                            <option value="link">Link / Page</option>
                            <option value="dropdown">Dropdown Menu</option>
                        </select>
                    </label>
                    <label>
                        Location
                        <select name="new_nav[location]">
                            <option value="header">Header</option>
                            <option value="footer">Footer</option>
                            <option value="mobile">Mobile</option>
                        </select>
                    </label>
                    <label>
                        Section
                        <select name="new_nav[section_key]" id="newNavSectionKey">
                            <option value="primary">Header Primary</option>
                            <option value="resources">Header Resources / Footer Resources</option>
                            <option value="marketplace">Header Marketplace</option>
                            <option value="platform">Footer Platform</option>
                            <option value="legal">Footer Legal</option>
                            <option value="bottom">Footer Bottom / Mobile Bottom</option>
                            <?php foreach ($custom_section_options as $compound => $section_opt): ?>
                                <?php if (in_array($section_opt['section_key'], ['primary', 'resources', 'marketplace', 'platform', 'legal', 'bottom'], true)) continue; ?>
                                <option value="<?php echo htmlspecialchars((string) $section_opt['section_key'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $section_opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__new__">➕ Create new section...</option>
                        </select>
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;">
                    <label>
                        Children Section (for dropdowns)
                        <select name="new_nav[children_section_key]" id="newNavChildrenSection">
                            <option value="">-- None --</option>
                            <option value="resources">Header Resources</option>
                            <option value="marketplace">Header Marketplace</option>
                            <?php foreach ($custom_section_options as $compound => $section_opt): ?>
                                <?php if (in_array($section_opt['section_key'], ['resources', 'marketplace'], true)) continue; ?>
                                <?php if ($section_opt['location'] !== 'header') continue; ?>
                                <option value="<?php echo htmlspecialchars((string) $section_opt['section_key'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $section_opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__new__">➕ Create new section...</option>
                        </select>
                    </label>
                    <label>
                        Audience
                        <select name="new_nav[audience]">
                            <option value="all">All Users</option>
                            <option value="guest">Guest Only</option>
                            <option value="member">Signed-in Only</option>
                        </select>
                    </label>
                    <label>
                        Order
                        <input type="number" name="new_nav[sort_order]" value="100">
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;">
                    <label>
                        Icon class
                        <input type="text" name="new_nav[icon_class]" placeholder="fas fa-link">
                    </label>
                    <label>
                        Badge
                        <input type="text" name="new_nav[badge_text]" placeholder="NEW">
                    </label>
                </div>
                <div class="launch-nav-grid-3" style="margin-top:10px;" id="newSectionKeyRow">
                    <label>
                        New Section Key
                        <input type="text" name="new_nav[new_section_key]" id="newNavSectionKeyInput" placeholder="e.g. community, tools, earn" pattern="[a-z0-9_]{2,40}">
                    </label>
                    <label>
                        <span style="color:#64748b; font-size:11px; line-height:1.5; display:block; margin-top:22px;">
                            <i class="fas fa-info-circle"></i> Lowercase letters, numbers, underscores only (2-40 chars).
                        </span>
                    </label>
                </div>
                <div class="launch-create-actions" style="margin-top:14px;">
                    <button type="submit" class="btn btn-primary">Add Link/Page</button>
                    <span class="launch-defaults">Tip: use Header Primary for desktop slots, or Mobile Bottom for mobile slots.</span>
                </div>
            </form>
        </section>

    </div>
</section>
</div>

<script>
(function() {
    // Edit Navigation Item form auto-populate
    const editNavItemSelect = document.getElementById('editNavItemSelect');
    const editNavLabel = document.getElementById('editNavLabel');
    const editNavUrl = document.getElementById('editNavUrl');
    const editNavIcon = document.getElementById('editNavIcon');
    const editNavBadge = document.getElementById('editNavBadge');
    const editNavOrder = document.getElementById('editNavOrder');
    const editNavEnabled = document.getElementById('editNavEnabled');

    if (editNavItemSelect) {
        editNavItemSelect.addEventListener('change', function() {
            const selected = editNavItemSelect.options[editNavItemSelect.selectedIndex];
            if (!selected || !selected.value) return;
            if (editNavLabel) editNavLabel.value = selected.dataset.label || '';
            if (editNavUrl) editNavUrl.value = selected.dataset.url || '';
            if (editNavIcon) editNavIcon.value = selected.dataset.icon || '';
            if (editNavBadge) editNavBadge.value = selected.dataset.badge || '';
            if (editNavOrder) editNavOrder.value = selected.dataset.order || '100';
            if (editNavEnabled) editNavEnabled.value = selected.dataset.enabled || '1';
        });
    }

    const itemTypeSelect = document.getElementById('newNavItemType');
    const childrenSectionSelect = document.getElementById('newNavChildrenSection');
    const sectionKeySelect = document.getElementById('newNavSectionKey');
    const customUrlInput = document.querySelector('input[name="new_nav[custom_url]"]');
    const newSectionKeyRow = document.getElementById('newSectionKeyRow');
    const newSectionKeyInput = document.getElementById('newNavSectionKeyInput');

    function updateCreateForm() {
        if (!itemTypeSelect) return;
        const isDropdown = itemTypeSelect.value === 'dropdown';
        if (childrenSectionSelect) {
            childrenSectionSelect.closest('label').style.display = isDropdown ? 'grid' : 'none';
        }
        if (sectionKeySelect) {
            sectionKeySelect.closest('label').style.display = isDropdown ? 'none' : 'grid';
        }
        if (customUrlInput) {
            customUrlInput.closest('label').style.display = isDropdown ? 'none' : 'grid';
            customUrlInput.required = !isDropdown;
        }
        updateNewSectionRow();
    }

    function updateNewSectionRow() {
        if (!newSectionKeyRow) return;
        const isDropdown = itemTypeSelect && itemTypeSelect.value === 'dropdown';
        const childrenIsNew = childrenSectionSelect && childrenSectionSelect.value === '__new__';
        const sectionIsNew = sectionKeySelect && sectionKeySelect.value === '__new__';
        const shouldShow = (isDropdown && childrenIsNew) || (!isDropdown && sectionIsNew);
        newSectionKeyRow.style.display = shouldShow ? 'grid' : 'none';
        if (newSectionKeyInput) {
            newSectionKeyInput.required = shouldShow;
        }
    }

    if (itemTypeSelect) {
        itemTypeSelect.addEventListener('change', updateCreateForm);
    }
    if (childrenSectionSelect) {
        childrenSectionSelect.addEventListener('change', updateNewSectionRow);
    }
    if (sectionKeySelect) {
        sectionKeySelect.addEventListener('change', updateNewSectionRow);
    }
    updateCreateForm();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
