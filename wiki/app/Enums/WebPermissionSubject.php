<?php

namespace App\Enums;

enum WebPermissionSubject: string
{
    case User = 'user';
    case Group = 'group';
    case Authenticated = 'authenticated';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Benutzer',
            self::Group => 'Gruppe',
            self::Authenticated => 'Alle angemeldeten Benutzer',
            self::Public => 'Öffentliche Gäste',
        };
    }
}
