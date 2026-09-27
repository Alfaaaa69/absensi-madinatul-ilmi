<?php

namespace Tests\Unit\Support;

use App\Support\NavLink;
use Tests\TestCase;

class NavLinkTest extends TestCase
{
    public function test_link_menyimpan_label_route_dan_icon(): void
    {
        $link = new NavLink('Dashboard', 'dashboard', 'home');

        $this->assertSame('Dashboard', $link->label);
        $this->assertSame('dashboard', $link->routeName);
        $this->assertSame('home', $link->icon);
    }

    public function test_link_bukan_admin_terlihat_untuk_semua_peran(): void
    {
        $link = new NavLink('Dashboard', 'dashboard', 'home');

        $this->assertTrue($link->visibleTo(false));
        $this->assertTrue($link->visibleTo(true));
    }

    public function test_link_admin_only_tidak_terlihat_untuk_guru(): void
    {
        $link = NavLink::adminOnly('Manajemen User', 'user.index', 'user');

        $this->assertTrue($link->adminOnly);
        $this->assertFalse($link->visibleTo(false));
    }

    public function test_link_admin_only_terlihat_untuk_admin(): void
    {
        $link = NavLink::adminOnly('Manajemen User', 'user.index', 'user');

        $this->assertTrue($link->visibleTo(true));
    }

    public function test_nav_link_menjadi_array_blade(): void
    {
        $link = new NavLink('Dashboard', 'dashboard', 'home');

        $this->assertSame(
            ['label' => 'Dashboard', 'routeName' => 'dashboard', 'icon' => 'home', 'adminOnly' => false],
            $link->toArray()
        );
    }
}
