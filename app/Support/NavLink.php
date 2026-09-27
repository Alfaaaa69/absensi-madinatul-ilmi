<?php

namespace App\Support;

class NavLink
{
    public function __construct(
        public readonly string $label,
        public readonly string $routeName,
        public readonly string $icon,
        public readonly bool $adminOnly = false,
    ) {}

    public static function adminOnly(
        string $label,
        string $routeName,
        string $icon,
    ): self {
        return new self($label, $routeName, $icon, true);
    }

    public function visibleTo(bool $isAdmin): bool
    {
        return ! $this->adminOnly || $isAdmin;
    }

    /**
     * @return array{label: string, routeName: string, icon: string, adminOnly: bool}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'adminOnly' => $this->adminOnly,
        ];
    }
}
