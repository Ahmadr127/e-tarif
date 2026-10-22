<?php

/*
|--------------------------------------------------------------------------
| Menu Sidebar
|--------------------------------------------------------------------------
|
| Struktur menu sidebar berbasis konfigurasi.
| Setiap grup punya 'title' + 'menus'.
| Item menu bisa berupa:
|   - Link tunggal  : 'permission' (string) + 'route' + 'route_pattern' + 'icon'
|   - Dropdown      : 'children' (array link tunggal), tampil jika salah satu child bisa diakses
|
| Setiap item hanya memakai satu 'permission' (tidak ada 'permissions' array).
|
| 'route_pattern' dipakai untuk menentukan status aktif (request()->routeIs).
| Ikon memakai Font Awesome tanpa prefix, contoh: 'fa-tachometer-alt'.
|
*/

return [
    [
        // 'title' => 'Menu Utama',
        'menus' => [
            [
                'label' => 'Dashboard',
                'icon' => 'bi-grid-fill',
                'route' => 'dashboard',
                'route_pattern' => 'dashboard',
                'permission' => 'view_dashboard',
            ],
        ],
    ],
    [
        // 'title' => 'Pengaturan',
        'menus' => [
            [
                'label' => 'Pengguna & Akses',
                'icon' => 'bi-person-fill-gear',
                'children' => [
                    [
                        'label' => 'Users',
                        'icon' => 'bi-person-fill-gear',
                        'route' => 'users.index',
                        'route_pattern' => 'users.*',
                        'permission' => 'manage_users',
                    ],
                    [
                        'label' => 'Roles',
                        'icon' => 'bi-person-fill-check',
                        'route' => 'roles.index',
                        'route_pattern' => 'roles.*',
                        'permission' => 'manage_roles',
                    ],
                    [
                        'label' => 'Permissions',
                        'icon' => 'bi-key-fill',
                        'route' => 'permissions.index',
                        'route_pattern' => 'permissions.*',
                        'permission' => 'manage_permissions',
                    ],
                ],
            ],
        ],
    ],
    [
        // 'title' => 'Organisasi',
        'menus' => [
            [
                'label' => 'Organisasi',
                'icon' => 'bi-buildings-fill',
                'children' => [
                    [
                        'label' => 'Tipe Organisasi',
                        'icon' => 'bi-diagram-3-fill',
                        'route' => 'organization-types.index',
                        'route_pattern' => 'organization-types.*',
                        'permission' => 'manage_organization_types',
                    ],
                    [
                        'label' => 'Unit Organisasi',
                        'icon' => 'bi-buildings-fill',
                        'route' => 'organization-units.index',
                        'route_pattern' => 'organization-units.*',
                        'permission' => 'manage_organization_units',
                    ],
                ],
            ],
        ],
    ],
    [
        // 'title' => 'Management Tarif',
        'menus' => [
            [
                'label' => 'Tarif',
                'icon' => 'bi-receipt',
                'children' => [
                    [
                        'label' => 'Daftar Tarif',
                        'icon' => 'bi-cash-coin',
                        'route' => 'tarifs.index',
                        'route_pattern' => 'tarifs.*',
                        'permission' => 'manage_tarifs',
                    ],
                    [
                        'label' => 'Buku Tarif Admin',
                        'icon' => 'bi-journal-bookmark-fill',
                        'route' => 'tarif-units.index',
                        'route_pattern' => 'tarif-units.*',
                        'permission' => 'manage_tarif_units',
                    ],
                    [
                        'label' => 'Buku Tarif',
                        'icon' => 'bi-journal-bookmark',
                        'route' => 'buku-tarif-saya.index',
                        'route_pattern' => 'buku-tarif-saya.*',
                        'permission' => 'manage_my_tarif_unit',
                    ],
                ],
            ],
            [
                'label' => 'Unit Cost',
                'icon' => 'bi-calculator',
                'children' => [
                    [
                        'label' => 'Periode',
                        'icon' => 'bi-calendar3',
                        'route' => 'calculations.index',
                        'route_pattern' => ['calculations.index', 'calculations.periods.*', 'calculations.parameters.*'],
                        'permission' => 'manage_unit_cost_periods',
                    ],
                    // [
                    //     'label' => 'Master',
                    //     'icon' => 'bi-sliders',
                    //     'route' => 'calculations.master',
                    //     'route_pattern' => 'calculations.master*',
                    //     'permission' => 'manage_unit_cost_masters',
                    // ],
                    [
                        'label' => 'Riwayat Hasil',
                        'icon' => 'bi-clock-history',
                        'route' => 'calculations.history.index',
                        'route_pattern' => 'calculations.history.*',
                        'permission' => 'view_unit_cost_results',
                    ],
                ],
            ],
            [
                'label' => 'Master',
                'icon' => 'bi-cash-coin',
                'children' => [
                    [
                        'label' => 'Komponen',
                        'icon' => 'bi-diagram-3-fill',
                        'route' => 'calculations.master',
                        'route_pattern' => ['calculations.master', 'calculations.master.components.*'],
                        'query' => ['section' => ['components', null]],
                        'permission' => 'manage_unit_cost_components',
                    ],
                    [
                        'label' => 'Parameter',
                        'icon' => 'bi-list-ul',
                        'route' => 'calculations.master',
                        'params' => ['section' => 'parameters'],
                        'route_pattern' => ['calculations.master', 'calculations.master.parameters.*'],
                        'query' => ['section' => 'parameters'],
                        'permission' => 'manage_unit_cost_parameters',
                    ],
                    [
                        'label' => 'Riwayat Formula',
                        'icon' => 'bi-clock-history',
                        'route' => 'calculations.master',
                        'params' => ['section' => 'formula-history'],
                        'route_pattern' => 'calculations.master',
                        'query' => ['section' => 'formula-history'],
                        'permission' => 'view_unit_cost_formula_history',
                    ],
                    [
                        'label' => 'Jenis Tarif',
                        'icon' => 'bi-tags-fill',
                        'route' => 'jenis-tarifs.index',
                        'route_pattern' => 'jenis-tarifs.*',
                        'permission' => 'manage_tarif_masters',
                    ],
                    [
                        'label' => 'Provider',
                        'icon' => 'bi-building-fill',
                        'route' => 'providers.index',
                        'route_pattern' => 'providers.*',
                        'permission' => 'manage_tarif_masters',
                    ],
                    [
                        'label' => 'Service',
                        'icon' => 'bi-gear-fill',
                        'route' => 'services.index',
                        'route_pattern' => 'services.*',
                        'permission' => 'manage_tarif_masters',
                    ],
                    [
                        'label' => 'Kelas',
                        'icon' => 'bi-layers-fill',
                        'route' => 'classes.index',
                        'route_pattern' => 'classes.*',
                        'permission' => 'manage_tarif_masters',
                    ],
                    [
                        'label' => 'Helper',
                        'icon' => 'bi-percent',
                        'route' => 'helpers.index',
                        'route_pattern' => 'helpers.*',
                        'permission' => 'manage_helpers',
                    ],
                    [
                        'label' => 'Import Excel',
                        'icon' => 'bi-file-earmark-spreadsheet',
                        'route' => 'tarif-import.index',
                        'route_pattern' => 'tarif-import.*',
                        'permission' => 'manage_tarifs',
                    ],
                ],
            ],
        ],
    ],
    [
        // 'title' => 'Laporan',
        'menus' => [
            [
                'label' => 'Audit Log',
                'icon' => 'bi-clock-history',
                'route' => 'activity-logs.index',
                'route_pattern' => 'activity-logs.*',
                'permission' => 'view_activity_logs',
            ],
        ],
    ],
];
