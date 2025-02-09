<?php

// Pandora FMS- http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2021 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list
// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
global $config;

require_once $config['homedir'].'/include/functions_agents.php';
require_once $config['homedir'].'/include/functions_reporting.php';
enterprise_include_once('include/functions_metaconsole.php');

// Clean the possible blanks introduced by the included files
ob_clean();

// Get list of agent + ip
// Params:
// * search_agents 1
// * id_agent
// * q
// * id_group
$search_agents = (bool) get_parameter('search_agents');
$get_agents_interfaces = (bool) get_parameter('get_agents_interfaces');
$id_agents = get_parameter('id_agents', []);
$get_agents_group = (bool) get_parameter('get_agents_group', false);
$force_local = (bool) get_parameter('force_local', false);
if (https_is_running()) {
    header('Content-type: application/json');
}

if ($get_agents_interfaces) {
    $agents_interfaces = agents_get_network_interfaces($id_agents);

    // Include alias per agent.
    foreach ($agents_interfaces as $key => $value) {
        $agent_alias = agents_get_alias($key);
        $agents_interfaces[$key]['agent_alias'] = $agent_alias;
    }

    echo json_encode($agents_interfaces);

    return;
}

if ($get_agents_group) {
    $id_group = (int) get_parameter('id_group', -1);
    $mode = (string) get_parameter('mode', 'json');
    $id_server = (int) get_parameter('id_server', 0);
    $serialized = (bool) get_parameter('serialized');

    $return = [];
    if ($id_group != -1) {
        $filter = [];

        if (is_metaconsole() && !empty($id_server)) {
            $filter['id_server'] = $id_server;
        }

        $return = agents_get_group_agents($id_group, $filter, 'none', false, false, $serialized);
    }

    switch ($mode) {
        case 'json':
        default:
            echo json_encode($return);
        break;
    }

    return;
}

if ($search_agents && (!is_metaconsole() || $force_local)) {
    $id_agent = (int) get_parameter('id_agent');
    $string = (string) get_parameter('q');
    $string = strtoupper($string);
    // q is what autocomplete plugin gives
    $id_group = (int) get_parameter('id_group', -1);
    $addedItems = html_entity_decode((string) get_parameter('add'));
    $addedItems = json_decode($addedItems);
    $all = (string) get_parameter('all', 'all');

    $delete_offspring_agents = (int) get_parameter('delete_offspring_agents', 0);

    if ($addedItems != null) {
        foreach ($addedItems as $item) {
            echo $item."|\n";
        }
    }

    $filter = [];

    if ($id_group != -1) {
        if ($id_group == 0) {
            $user_groups = users_get_groups($config['id_user'], 'AR', true);

            $filter['id_grupo'] = array_keys($user_groups);
        } else {
            $filter['id_grupo'] = $id_group;
        }
    }

    if ($all === 'enabled') {
        $filter['disabled'] = 1;
    } else {
        $filter['disabled'] = 0;
    }

    $data = [];
    // Get agents for only the alias.
    $filter_alias = $filter;
    $filter_alias[] = '(UPPER(alias) LIKE "%'.$string.'%")';

    $agents = agents_get_agents($filter_alias, ['id_agente', 'nombre', 'direccion', 'alias']);
    if ($agents !== false) {
        foreach ($agents as $agent) {
            $data[] = [
                'id'     => $agent['id_agente'],
                'name'   => io_safe_output($agent['nombre']),
                'alias'  => io_safe_output($agent['alias']),
                'ip'     => io_safe_output($agent['direccion']),
                'filter' => 'alias',
            ];
        }
    }

    // Get agents for only the name.
    $filter_agents = $filter;
    $filter_agents[] = '(UPPER(alias) NOT LIKE "%'.$string.'%" AND UPPER(nombre) LIKE "%'.$string.'%")';

    $agents = agents_get_agents($filter_agents, ['id_agente', 'nombre', 'direccion', 'alias']);
    if ($agents !== false) {
        foreach ($agents as $agent) {
            $data[] = [
                'id'     => $agent['id_agente'],
                'name'   => io_safe_output($agent['nombre']),
                'alias'  => io_safe_output($agent['alias']),
                'ip'     => io_safe_output($agent['direccion']),
                'filter' => 'agent',
            ];
        }
    }

    // Get agents for only the address.
    $filter_address = $filter;
    $filter_address[] = '(UPPER(alias) NOT LIKE "%'.$string.'%" AND UPPER(nombre) NOT LIKE "%'.$string.'%" AND UPPER(direccion) LIKE "%'.$string.'%")';

    $agents = agents_get_agents($filter_address, ['id_agente', 'nombre', 'direccion', 'alias']);
    if ($agents !== false) {
        foreach ($agents as $agent) {
            $data[] = [
                'id'     => $agent['id_agente'],
                'name'   => io_safe_output($agent['nombre']),
                'alias'  => io_safe_output($agent['alias']),
                'ip'     => io_safe_output($agent['direccion']),
                'filter' => 'address',
            ];
        }
    }

    // Get agents for only the description.
    $filter_description = $filter;
    $filter_description[] = '(UPPER(alias) NOT LIKE "%'.$string.'%" AND UPPER(nombre) NOT LIKE "%'.$string.'%" AND UPPER(direccion) NOT LIKE "%'.$string.'%" AND UPPER(comentarios) LIKE "%'.$string.'%")';

    $agents = agents_get_agents($filter_description, ['id_agente', 'nombre', 'direccion', 'alias']);
    if ($agents !== false) {
        foreach ($agents as $agent) {
            $data[] = [
                'id'     => $agent['id_agente'],
                'name'   => io_safe_output($agent['nombre']),
                'alias'  => io_safe_output($agent['alias']),
                'ip'     => io_safe_output($agent['direccion']),
                'filter' => 'description',
            ];
        }
    }

    if (empty($data) === false && $delete_offspring_agents !== 0) {
        // Gets offspring and deletes them, including himself.
        $agents_offspring = agents_get_offspring($delete_offspring_agents);
        if (empty($agents_offspring) === false) {
            foreach ($data as $key => $value) {
                if (isset($agents_offspring[$value['id']]) === true) {
                    unset($data[$key]);
                }
            }
        }
    }

    echo json_encode($data);
    return;
} else if ($search_agents && is_metaconsole()) {
    $id_agent = (int) get_parameter('id_agent');
    $string = (string) get_parameter('q');
    // Q is what autocomplete plugin gives.
    $id_group = (int) get_parameter('id_group', -1);
    $addedItems = html_entity_decode((string) get_parameter('add'));
    $addedItems = json_decode($addedItems);
    $all = (string) get_parameter('all', 'all');

    if ($addedItems != null) {
        foreach ($addedItems as $item) {
            echo $item."|\n";
        }
    }

    $data = [];

    $fields = [
        'id_tagente AS id_agente',
        'nombre',
        'alias',
        'direccion',
        'id_tmetaconsole_setup AS id_server',
        'server_name',
    ];

    $filter = [];

    if ($id_group != -1) {
        if ($id_group == 0) {
            $user_groups = users_get_groups($config['id_user'], 'AR', true);

            $filter['id_grupo'] = array_keys($user_groups);
        } else {
            $filter['id_grupo'] = $id_group;
        }
    }

    switch ($all) {
        case 'enabled':
            $filter['disabled'] = 0;
        break;

        default:
            // Not possible.
        break;
    }

    if (empty($id_agent) === false) {
        $filter['id_agente'] = $id_agent;
    }

    if (empty($string) === false) {
        // Get agents for only the alias.
        $filter_alias = $filter;
        $filter_alias[] = '(alias LIKE "%'.$string.'%")';

        $agents = db_get_all_rows_filter(
            'tmetaconsole_agent',
            $filter_alias,
            $fields
        );

        if ($agents !== false) {
            foreach ($agents as $agent) {
                $data[] = [
                    'id'        => $agent['id_agente'],
                    'name'      => io_safe_output($agent['nombre']),
                    'alias'     => io_safe_output($agent['alias']).' ('.io_safe_output($agent['server_name']).')',
                    'ip'        => io_safe_output($agent['direccion']),
                    'id_server' => $agent['id_server'],
                    'filter'    => 'alias',
                ];
            }
        }

        // Get agents for only the name.
        $filter_agents = $filter;
        $filter_agents[] = '(alias NOT LIKE "%'.$string.'%" AND nombre LIKE "%'.$string.'%")';

        $agents = db_get_all_rows_filter(
            'tmetaconsole_agent',
            $filter_agents,
            $fields
        );

        if ($agents !== false) {
            foreach ($agents as $agent) {
                $data[] = [
                    'id'        => $agent['id_agente'],
                    'name'      => io_safe_output($agent['nombre']),
                    'alias'     => io_safe_output($agent['alias']).' ('.io_safe_output($agent['server_name']).')',
                    'ip'        => io_safe_output($agent['direccion']),
                    'id_server' => $agent['id_server'],
                    'filter'    => 'agent',
                ];
            }
        }

        // Get agents for only the address.
        $filter_address = $filter;
        $filter_address[] = '(alias NOT LIKE "%'.$string.'%" AND nombre NOT LIKE "%'.$string.'%" AND direccion LIKE "%'.$string.'%")';

        $agents = db_get_all_rows_filter(
            'tmetaconsole_agent',
            $filter_address,
            $fields
        );

        if ($agents !== false) {
            foreach ($agents as $agent) {
                $data[] = [
                    'id'        => $agent['id_agente'],
                    'name'      => io_safe_output($agent['nombre']),
                    'alias'     => io_safe_output($agent['alias']).' ('.io_safe_output($agent['server_name']).')',
                    'ip'        => io_safe_output($agent['direccion']),
                    'id_server' => $agent['id_server'],
                    'filter'    => 'address',
                ];
            }
        }

        // Get agents for only the description.
        $filter_description = $filter;
        $filter_description[] = '(alias NOT LIKE "%'.$string.'%" AND nombre NOT LIKE "%'.$string.'%" AND direccion NOT LIKE "%'.$string.'%" AND comentarios LIKE "%'.$string.'%")';

        $agents = db_get_all_rows_filter(
            'tmetaconsole_agent',
            $filter_description,
            $fields
        );

        if ($agents !== false) {
            foreach ($agents as $agent) {
                $data[] = [
                    'id'        => $agent['id_agente'],
                    'name'      => io_safe_output($agent['nombre']),
                    'alias'     => io_safe_output($agent['alias']).' ('.io_safe_output($agent['server_name']).')',
                    'ip'        => io_safe_output($agent['direccion']),
                    'id_server' => $agent['id_server'],
                    'filter'    => 'description',
                ];
            }
        }
    }

    echo json_encode($data);
    return;
}

return;
