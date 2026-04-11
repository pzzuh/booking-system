<?php
/**
 * PATCH FOR includes/functions.php
 * 
 * Find the approvalRoleBadge() function and replace it entirely with this version.
 * This fixes the role display labels throughout the system.
 */

function approvalRoleBadge(string $role): string {
    $map = [
        'adviser'        => ['Adviser',                       'primary'],
        'staff'          => ['Staff',                         'secondary'],
        'dean'           => ['Dean',                          'info'],
        'dsa_director'   => ['DSA Director',                  'warning'],
        'ppss_director'  => ['PPSS Director',                 'warning'],
        'avp_admin'      => ['Administrative Vice President', 'danger'],
        'vp_admin'       => ['Vice President',                'danger'],
        'president'      => ['President',                     'dark'],
        'admin'          => ['Admin',                         'dark'],
        'janitor'        => ['Janitorial',                    'secondary'],
        'security'       => ['Security',                      'secondary'],
    ];

    if ($role === '' || $role === null) {
        return '<span class="badge bg-success">Fully Approved</span>';
    }

    [$label, $color] = $map[$role] ?? [ucwords(str_replace('_', ' ', $role)), 'secondary'];
    return '<span class="badge bg-' . e($color) . '">' . e($label) . '</span>';
}
