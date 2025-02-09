<?php
// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2021 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// Login check
global $config;

check_login();

// Visual console required
if (empty($visualConsole)) {
    db_pandora_audit(
        AUDIT_LOG_ACL_VIOLATION,
        'Trying to access report builder'
    );
    include 'general/noaccess.php';
    exit;
}

// ACL for the existing visual console
// if (!isset($vconsole_read))
// $vconsole_read = check_acl ($config['id_user'], $visualConsole['id_group'], "VR");
if (!isset($vconsole_write)) {
    $vconsole_write = check_acl(
        $config['id_user'],
        $visualConsole['id_group'],
        'VW'
    );
}

if (!isset($vconsole_manage)) {
    $vconsole_manage = check_acl(
        $config['id_user'],
        $visualConsole['id_group'],
        'VM'
    );
}

if (!$vconsole_write && !$vconsole_manage) {
    db_pandora_audit(
        AUDIT_LOG_ACL_VIOLATION,
        'Trying to access report builder'
    );
    include 'general/noaccess.php';
    exit;
}

require_once $config['homedir'].'/include/functions_visual_map.php';
require_once $config['homedir'].'/include/functions_agents.php';
enterprise_include_once('include/functions_visual_map.php');
enterprise_include_once('meta/include/functions_agents_meta.php');
enterprise_include_once('meta/include/functions_users_meta.php');

// Arrays for select box.
$backgrounds_list = list_files(
    $config['homedir'].'/images/console/background/',
    'jpg',
    1,
    0
);
$backgrounds_list = array_merge(
    $backgrounds_list,
    list_files($config['homedir'].'/images/console/background/', 'png', 1, 0)
);

$images_list = [];
$all_images = list_files(
    $config['homedir'].'/images/console/icons/',
    'png',
    1,
    0
);
foreach ($all_images as $image_file) {
    if (strpos($image_file, '_bad')) {
        continue;
    }

    if (strpos($image_file, '_ok')) {
        continue;
    }

    if (strpos($image_file, '_warning')) {
        continue;
    }

    $image_file = substr($image_file, 0, (strlen($image_file) - 4));
    $images_list[$image_file] = $image_file;
}

$table = new stdClass();
$table->width = '100%';
$table->class = 'databox data';

$table->head = [];
$table->head['icon'] = '';
$table->head[0] = __('Label').'<br>'.__('Agent').' / '.__('Group');
$table->head[1] = __('Image').'<br>'.__('Module').' / '.__('Custom graph');
$table->head[2] = __('Width x Height<br>Max value');
$table->head[3] = __('Position').'<br>'.__('Period');
$table->head[4] = __('Parent').'<br>'.__('Map linked');
$table->head[5] = '';
$table->head[5] .= '&nbsp;&nbsp;&nbsp;'.html_print_checkbox(
    'head_multiple_delete',
    '',
    false,
    true,
    false,
    'toggle_checkbox_multiple_delete();'
);
$table->head[5] .= '&nbsp;&nbsp;&nbsp;<span title="'.__('Action').'">'.__('A.').'</span>';

$table->size = [];
$table->size['icon'] = '1%';
$table->size[0] = '28%';
$table->size[1] = '';
$table->size[2] = '25%';
$table->size[3] = '27%';
$table->size[4] = '7%';
$table->size[5] = '15%';


$table->align = [];

if (!defined('METACONSOLE')) {
    $table->headstyle[0] = 'text-align:left;';
    $table->headstyle[1] = 'text-align:left';
    $table->headstyle[2] = 'text-align:left';
    $table->headstyle[3] = 'text-align:left';
    $table->headstyle[4] = 'text-align:left';
    $table->headstyle[5] = 'text-align:left';
    $table->align[0] = 'left';
    $table->align[1] = 'left';
    $table->align[2] = 'left';
    $table->align[3] = 'left';
    $table->align[4] = 'left';
    $table->align[5] = 'left';
}

$table->data = [];

// Background
$table->data[0]['icon'] = '';
$table->data[0][0] = '<div sclass="invisible">'.__('Background').'</div>';
$table->data[0][1] = '<div sclass="invisible">'.html_print_select($backgrounds_list, 'background', $visualConsole['background'], '', 'None', '', true, false, true, '', false, 'width: 120px;').'</div>';
$table->data[0][2] = '<div sclass="invisible">'.html_print_input_text('width', $visualConsole['width'], '', 3, 5, true).' x '.html_print_input_text('height', $visualConsole['height'], '', 3, 5, true).'</div>';
$table->data[0][3] = $table->data[0][4] = $table->data[0][5] = '';

$i = 1;
$layoutDatas = db_get_all_rows_field_filter(
    'tlayout_data',
    'id_layout',
    $idVisualConsole
);
if ($layoutDatas === false) {
    $layoutDatas = [];
}

$alternativeStyle = true;

$parents = visual_map_get_items_parents($idVisualConsole);

foreach ($layoutDatas as $layoutData) {
    $idLayoutData = $layoutData['id'];

    // line between rows
    $table->data[$i][0] = '<hr>';
    $table->colspan[$i][0] = '8';

    switch ($layoutData['type']) {
        case STATIC_GRAPH:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/camera_mc.png',
                true,
                [
                    'title' => __('Static Image'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case PERCENTILE_BAR:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/chart_bar.png',
                true,
                [
                    'title' => __('Percentile Bar'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case PERCENTILE_BUBBLE:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/dot_red.png',
                true,
                ['title' => __('Percentile Bubble')]
            );
        break;

        case CIRCULAR_INTERIOR_PROGRESS_BAR:
        case CIRCULAR_PROGRESS_BAR:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/percentile_item.png',
                true,
                ['title' => __('Percentile')]
            );
        break;

        case MODULE_GRAPH:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/chart_curve.png',
                true,
                [
                    'title' => __('Module Graph'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case AUTO_SLA_GRAPH:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/auto_sla_graph.png',
                true,
                ['title' => __('Event history graph')]
            );
        break;

        case SIMPLE_VALUE:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/binary.png',
                true,
                [
                    'title' => __('Simple Value'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case SIMPLE_VALUE_MAX:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/binary.png',
                true,
                [
                    'title' => __('Simple Value (Process Max)'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case SIMPLE_VALUE_MIN:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/binary.png',
                true,
                [
                    'title' => __('Simple Value (Process Min)'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case SIMPLE_VALUE_AVG:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/binary.png',
                true,
                [
                    'title' => __('Simple Value (Process Avg)'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case LABEL:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/tag_red.png',
                true,
                ['title' => __('Label')]
            );
        break;

        case ICON:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/photo.png',
                true,
                [
                    'title' => __('Icon'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case BOX_ITEM:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/box_item.png',
                true,
                [
                    'title' => __('Box'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case GROUP_ITEM:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/group_green.png',
                true,
                ['title' => __('Group')]
            );
        break;

        case NETWORK_LINK:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/network_link_item.png',
                true,
                [
                    'title' => __('Network link'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case LINE_ITEM:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/line_item.png',
                true,
                [
                    'title' => __('Line'),
                    'class' => 'invert_filter',
                ]
            );
        break;

        case COLOR_CLOUD:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/color_cloud_item.png',
                true,
                ['title' => __('Color cloud')]
            );
        break;

        case BASIC_CHART:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/basic_chart.png',
                true,
                ['title' => __('Basic chart')]
            );
        break;

        case ODOMETER:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/odometer.png',
                true,
                ['title' => __('Odometer')]
            );
        break;

        case CLOCK:
            $table->data[($i + 1)]['icon'] = html_print_image(
                'images/clock-tab.png',
                true,
                ['title' => __('Clock')]
            );
        break;

        default:
            if (enterprise_installed()) {
                $table->data[($i + 1)]['icon'] = enterprise_visual_map_print_list_element('icon', $layoutData);
            } else {
                $table->data[($i + 1)]['icon'] = '';
            }
        break;
    }



    // First row
    // Label
    switch ($layoutData['type']) {
        case ICON:
        case BOX_ITEM:
        case NETWORK_LINK:
        case LINE_ITEM:
            // hasn't the label.
            $table->data[($i + 1)][0] = '';
        break;

        default:
            $table->data[($i + 1)][0] = '<span class="w150px block">'.html_print_input_hidden('label_'.$idLayoutData, $layoutData['label'], true).'<a href="javascript: show_dialog_label_editor('.$idLayoutData.');">'.__('Edit label').'</a>'.'</span>';
        break;
    }


    // Image
    switch ($layoutData['type']) {
        case STATIC_GRAPH:
        case ICON:
        case GROUP_ITEM:
        case SERVICE:
            $table->data[($i + 1)][1] = html_print_select(
                $images_list,
                'image_'.$idLayoutData,
                $layoutData['image'],
                '',
                'None',
                '',
                true,
                false,
                true,
                '',
                false,
                'width: 120px'
            );
        break;

        default:
            $table->data[($i + 1)][1] = '';
        break;
    }



    // Width and height
    switch ($layoutData['type']) {
        case NETWORK_LINK:
        case LINE_ITEM:
            // hasn't the width and height.
            $table->data[($i + 1)][2] = '';
        break;

        case COLOR_CLOUD:
            $table->data[($i + 1)][2] = html_print_input_text('width_'.$idLayoutData, $layoutData['width'], '', 2, 5, true).' x '.html_print_input_text('height_'.$idLayoutData, $layoutData['width'], '', 2, 5, true);
        break;

        case CIRCULAR_PROGRESS_BAR:
        case CIRCULAR_INTERIOR_PROGRESS_BAR:
        case PERCENTILE_BUBBLE:
        case PERCENTILE_BAR:
            $table->data[($i + 1)][2] = html_print_input_text('width_'.$idLayoutData, $layoutData['width'], '', 2, 5, true);
        break;

        default:
            $table->data[($i + 1)][2] = html_print_input_text('width_'.$idLayoutData, $layoutData['width'], '', 2, 5, true).' x '.html_print_input_text('height_'.$idLayoutData, $layoutData['height'], '', 2, 5, true);
        break;
    }

    // Position
    switch ($layoutData['type']) {
        case NETWORK_LINK:
        case LINE_ITEM:
            // hasn't the width and height.
            $table->data[($i + 1)][3] = '';
        break;

        default:
            $table->data[($i + 1)][3] = '( '.html_print_input_text('left_'.$idLayoutData, $layoutData['pos_x'], '', 2, 5, true).' , '.html_print_input_text('top_'.$idLayoutData, $layoutData['pos_y'], '', 2, 5, true).' )';
        break;
    }


    // Parent
    switch ($layoutData['type']) {
        case BOX_ITEM:
        case NETWORK_LINK:
        case LINE_ITEM:
        case COLOR_CLOUD:
            $table->data[($i + 1)][4] = '';
        break;

        default:
            $table->data[($i + 1)][4] = html_print_select(
                $parents,
                'parent_'.$idLayoutData,
                $layoutData['parent_item'],
                '',
                __('None'),
                0,
                true
            );
    }

    // Delete row button
    if (!defined('METACONSOLE')) {
        $url_delete = 'index.php?'.'sec=network&'.'sec2=godmode/reporting/visual_console_builder&'.'tab='.$activeTab.'&'.'action=delete&'.'id_visual_console='.$visualConsole['id'].'&'.'id_element='.$idLayoutData;
    } else {
        $url_delete = 'index.php?'.'operation=edit_visualmap&'.'sec=screen&'.'sec2=screens/screens&'.'action=visualmap&'.'pure='.(int) get_parameter('pure', 0).'&'.'tab=list_elements&'.'action2=delete&'.'id_visual_console='.$visualConsole['id'].'&'.'id_element='.$idLayoutData;
    }

    $table->data[($i + 1)][5] = '';
    $table->data[($i + 1)][5] .= html_print_checkbox('multiple_delete_items', $idLayoutData, false, true);
    $table->data[($i + 1)][5] .= '<a href="'.$url_delete.'" '.'onclick="javascript: if (!confirm(\''.__('Are you sure?').'\')) return false;">'.html_print_image('images/cross.png', true, ['class' => 'invert_filter']).'</a>';

    // Second row
    $table->data[($i + 2)]['icon'] = '';


    // Agent
    switch ($layoutData['type']) {
        case GROUP_ITEM:
            $own_info = get_user_info($config['id_user']);
            if (!$own_info['is_admin'] && !check_acl($config['id_user'], 0, 'PM')) {
                $return_all_group = false;
            } else {
                $return_all_group = true;
            }

            $table->data[($i + 2)][0] = html_print_select_groups(
                false,
                'AR',
                $return_all_group,
                'group_'.$idLayoutData,
                $layoutData['id_group'],
                '',
                '',
                0,
                true
            );
        break;

        case BOX_ITEM:
        case ICON:
        case LABEL:
        case NETWORK_LINK:
        case LINE_ITEM:
        case CLOCK:
            $table->data[($i + 2)][0] = '';
        break;

        default:
            $cell_content_enterprise = false;
            if (enterprise_installed()) {
                $cell_content_enterprise = enterprise_visual_map_print_list_element('agent', $layoutData);
            }

            if ($cell_content_enterprise === false) {
                $params = [];
                $params['return'] = true;
                $params['show_helptip'] = true;
                $params['size'] = 20;
                $params['input_name'] = 'agent_'.$idLayoutData;
                $params['javascript_is_function_select'] = true;
                $params['selectbox_id'] = 'module_'.$idLayoutData;
                if (defined('METACONSOLE')) {
                    $params['javascript_ajax_page'] = '../../ajax.php';
                    $params['disabled_javascript_on_blur_function'] = true;

                    $params['print_input_id_server'] = true;
                    $params['input_id_server_id'] = $params['input_id_server_name'] = 'id_server_id_'.$idLayoutData;
                    $params['input_id_server_value'] = $layoutData['id_metaconsole'];
                    $params['metaconsole_enabled'] = true;
                    $params['print_hidden_input_idagent'] = true;
                    $params['hidden_input_idagent_name'] = 'id_agent_'.$idLayoutData;
                    $params['hidden_input_idagent_value'] = $layoutData['id_agent'];

                    $params['value'] = agents_meta_get_alias(
                        $layoutData['id_agent'],
                        'none',
                        $layoutData['id_metaconsole'],
                        true
                    );
                } else {
                    $params['print_hidden_input_idagent'] = true;
                    $params['hidden_input_idagent_name'] = 'id_agent_'.$idLayoutData;
                    $params['hidden_input_idagent_value'] = $layoutData['id_agent'];
                    $params['value'] = db_get_value('alias', 'tagente', 'id_agente', $layoutData['id_agent']);
                }

                if ($layoutData['id_custom_graph'] != 0) {
                    $table->data[($i + 2)][0] = __('Custom graph');
                } else {
                    $table->data[($i + 2)][0] = ui_print_agent_autocomplete_input($params);
                }
            } else {
                $table->data[($i + 2)][0] = $cell_content_enterprise;
            }
        break;
    }


    // Module
    switch ($layoutData['type']) {
        case ICON:
        case LABEL:
        case BOX_ITEM:
        case NETWORK_LINK:
        case LINE_ITEM:
        case GROUP_ITEM:
        case CLOCK:
            $table->data[($i + 2)][1] = '';
        break;

        default:
            if ($layoutData['id_layout_linked'] != 0) {
                // It is a item that links with other visualmap
                break;
            }

            $cell_content_enterprise = false;
            if (enterprise_installed()) {
                $cell_content_enterprise = enterprise_visual_map_print_list_element('module', $layoutData);
            }

            if ($cell_content_enterprise === false) {
                if (!defined('METACONSOLE')) {
                    $modules = agents_get_modules($layoutData['id_agent']);
                } else {
                    if ($layoutData['id_agent'] != 0) {
                        $server = db_get_row('tmetaconsole_setup', 'id', $layoutData['id_metaconsole']);
                        if (metaconsole_connect($server) == NOERR) {
                            $modules = agents_get_modules($layoutData['id_agent']);
                            metaconsole_restore_db();
                        }
                    }
                }

                $modules = io_safe_output($modules);

                if ($layoutData['id_custom_graph'] != 0) {
                    if (is_metaconsole()) {
                        $graphs = [];
                        $graphs = metaconsole_get_custom_graphs(true);
                        $table->data[($i + 2)][1] = html_print_select(
                            $graphs,
                            'custom_graph_'.$idLayoutData,
                            $layoutData['id_custom_graph'].'|'.$layoutData['id_metaconsole'],
                            '',
                            __('None'),
                            0,
                            true
                        );
                    } else {
                        $table->data[($i + 2)][1] = html_print_select_from_sql(
                            'SELECT id_graph, name FROM tgraph',
                            'custom_graph_'.$idLayoutData,
                            $layoutData['id_custom_graph'],
                            '',
                            __('None'),
                            0,
                            true
                        );
                    }
                } else {
                    $table->data[($i + 2)][1] = html_print_select(
                        $modules,
                        'module_'.$idLayoutData,
                        $layoutData['id_agente_modulo'],
                        '',
                        '---',
                        0,
                        true,
                        false,
                        true,
                        '',
                        false,
                        'width: 120px'
                    );
                }
            } else {
                $table->data[($i + 2)][1] = $cell_content_enterprise;
            }
        break;
    }

    // Empty
    $table->data[($i + 2)][2] = '';

    // Period
    switch ($layoutData['type']) {
        case MODULE_GRAPH:
        case SIMPLE_VALUE_MAX:
        case SIMPLE_VALUE_MIN:
        case SIMPLE_VALUE_AVG:
            $table->data[($i + 2)][3] = html_print_extended_select_for_time(
                'period_'.$idLayoutData,
                $layoutData['period'],
                '',
                '--',
                '0',
                10,
                true
            );
        break;

        default:
            $table->data[($i + 2)][3] = '';
        break;
    }

    // Map linked
    switch ($layoutData['type']) {
        case NETWORK_LINK:
        case LINE_ITEM:
        case BOX_ITEM:
        case AUTO_SLA_GRAPH:
        case COLOR_CLOUD:
            $table->data[($i + 2)][4] = '';
        break;

        default:
            $table->data[($i + 2)][4] = html_print_select_from_sql(
                'SELECT id, name
					FROM tlayout
					WHERE id != '.$idVisualConsole,
                'map_linked_'.$idLayoutData,
                $layoutData['id_layout_linked'],
                '',
                'None',
                '',
                true,
                false,
                true,
                '',
                false,
                'width: 120px'
            );
        break;
    }

    $table->data[($i + 2)][5] = '';

    if ($alternativeStyle) {
        $table->rowclass[($i + 1)] = 'rowOdd';
        $table->rowclass[($i + 2)] = 'rowOdd';
    } else {
        $table->rowclass[($i + 1)] = 'rowPair';
        $table->rowclass[($i + 2)] = 'rowPair';
    }

    $alternativeStyle = !$alternativeStyle;

    $i = ($i + 3);
}

$pure = get_parameter('pure', 0);

if (!defined('METACONSOLE')) {
    echo '<form class="vc_elem_form" method="post" action="index.php?sec=network&sec2=godmode/reporting/visual_console_builder&tab='.$activeTab.'&id_visual_console='.$visualConsole['id'].'">';
} else {
    echo "<form class='vc_elem_form' method='post' action='index.php?operation=edit_visualmap&sec=screen&sec2=screens/screens&action=visualmap&pure=0&tab=list_elements&id_visual_console=".$idVisualConsole."'>";
}

if (!defined('METACONSOLE')) {
    echo '<div class="action-buttons" style="width: '.$table->width.'; margin-bottom:15px;">';
}

if (!defined('METACONSOLE')) {
    html_print_input_hidden('action', 'update');
} else {
    html_print_input_hidden('action2', 'update');
}

html_print_table($table);

echo '<div class="action-buttons" style="width: '.$table->width.'">';
html_print_submit_button(__('Update'), 'go', false, 'class="sub next"');
echo '&nbsp;';
html_print_button(__('Delete'), 'delete', false, 'submit_delete_multiple_items();', 'class="sub delete"');
echo '</div>';
echo '</form>';

// Form for multiple delete
if (!defined('METACONSOLE')) {
    $url_multiple_delete = 'index.php?'.'sec=network&'.'sec2=godmode/reporting/visual_console_builder&'.'tab='.$activeTab.'&'.'id_visual_console='.$visualConsole['id'];

    echo '<form id="form_multiple_delete" method="post" action="'.$url_multiple_delete.'">';
} else {
    $url_multiple_delete = 'index.php?'.'operation=edit_visualmap&'.'sec=screen&'.'sec2=screens/screens&'.'action=visualmap&'.'pure=0&'.'tab=list_elements&'.'id_visual_console='.$idVisualConsole;

    echo "<form id='form_multiple_delete' method='post' action=".$url_multiple_delete.'>';
}

if (!defined('METACONSOLE')) {
    html_print_input_hidden('action', 'multiple_delete');
} else {
    html_print_input_hidden('action2', 'multiple_delete');
}

html_print_input_hidden('id_visual_console', $visualConsole['id']);
html_print_input_hidden('id_item_json', '');
echo '</form>';


// Trick for it have a traduct text for javascript.
echo '<span id="ip_text" class="invisible">'.__('IP').'</span>';
?>
<div id="dialog_label_editor">
    <input id="active_id_layout_data" type="hidden" />
    <textarea id="tinyMCE_editor" name="tinyMCE_editor"></textarea>
</div>
<?php
ui_require_css_file('color-picker', 'include/styles/js/');

ui_require_jquery_file('colorpicker');
ui_require_jquery_file('pandora.controls');
ui_require_javascript_file('wz_jsgraphics');
ui_require_javascript_file('pandora_visual_console');
ui_require_jquery_file('ajaxqueue');
ui_require_jquery_file('bgiframe');
ui_require_javascript_file('tiny_mce', 'include/javascript/tiny_mce/');
?>

<script type="text/javascript">
    $(document).ready (function () {
        $('form.vc_elem_form').submit(function() {
            var inputs_array = $(this).serializeArray();
            var form_action = {};

            form_action.name = 'go';
            form_action.value = 'Update';
            inputs_array.push(form_action);

            var serialized_form_inputs = JSON.stringify(inputs_array);
            var ajax_url = "<?php echo (is_metaconsole() === true) ? 'index.php?operation=edit_visualmap&sec=screen&sec2=screens/screens&action=visualmap&pure=0&tab=list_elements&id_visual_console='.$idVisualConsole : 'index.php?sec=network&sec2=godmode/reporting/visual_console_builder&tab='.$activeTab.'&id_visual_console='.$visualConsole['id']; ?>";

            $.post({
                url: ajax_url,
                data: { serialized_form_inputs },
                dataType: "json",
                async: false,
                complete: function (data) {
                    location.reload();
                }
            });

            return false;
        });

        var added_config = {
            "selector": "#tinyMCE_editor",
            "elements": "tinyMCE_editor",
            "plugins": "noneditable",
            "theme_advanced_buttons1": "bold,italic,|,justifyleft,justifycenter,justifyright,|,undo,redo,|,image,link,|,fontselect,|,forecolor,fontsizeselect,|,code",
            "valid_children": "+body[style]",
            "theme_advanced_font_sizes": "true",
            "content_css": <?php echo '"'.ui_get_full_url('include/styles/pandora.css', false, false, false).'"'; ?>,
            "editor_deselector": "noselected",
            "inline_styles": true,
            "nowrap": true,
            "width": "400",
            "height": "200",
            "body_class": "tinyMCEBody",
        }

        defineTinyMCE(added_config);
        $("#dialog_label_editor").hide ()
            .dialog ({
                title: "<?php echo __('Edit label'); ?>",
                resizable: false,
                draggable: true,
                modal: true,
                overlay: {
                    opacity: 0.5,
                    background: "black"
                },
                width: 530,
                height: 300,
                autoOpen: false,
                beforeClose: function() {
                    var id_layout_data = $("#active_id_layout_data").val();
                    var label = tinyMCE.activeEditor.getContent();
                    $("#hidden-label_" + id_layout_data).val(label);
                }
            });

        var idText = $("#ip_text").html();
    });

    function show_dialog_label_editor(id_layout_data) {
        var label = $("#hidden-label_" + id_layout_data).val();
        $("#active_id_layout_data").val(id_layout_data);
        tinyMCE.activeEditor.setContent(label);
        $("#dialog_label_editor").dialog("open");
    }

    function toggle_checkbox_multiple_delete() {
        checked_head_multiple = $("input[name='head_multiple_delete']")
            .is(":checked");
        $("input[name='multiple_delete_items']")
            .prop("checked", checked_head_multiple);
    }

    function submit_delete_multiple_items() {
        delete_items = [];
        jQuery.each($("input[name='multiple_delete_items']:checked"),
            function(i, item) {
                delete_items.push($(item).val());
            }
        );

        $("input[name='id_item_json']").val(JSON.stringify(delete_items));
        $("#form_multiple_delete").submit();
    }
</script>
