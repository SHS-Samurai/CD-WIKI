<?php

namespace App\Enums;

enum WebVisibility: string
{
    case Private = 'private';
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Groups = 'groups';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privat',
            self::Public => 'Öffentlich lesbar',
            self::Authenticated => 'Nur angemeldete Benutzer',
            self::Groups => 'Nur bestimmte Gruppen',
            self::Users => 'Nur bestimmte Benutzer',
        };
    }
}
