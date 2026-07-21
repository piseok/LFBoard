<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\Select;

trait HasLevelSelect
{
    // 회원 레벨(1~9)을 숫자 입력 대신 드롭다운으로 제공해, 범위를 벗어난 값을 아예 입력할 수 없게 한다.
    protected static function levelSelect(string $name, string $label, int $default = 1): Select
    {
        return Select::make($name)
            ->label($label)
            ->options([
                1 => '1 — 비회원',
                2 => '2 — 일반회원',
                3 => '3',
                4 => '4',
                5 => '5',
                6 => '6',
                7 => '7',
                8 => '8',
                9 => '9 — 관리자',
            ])
            ->native(false)
            ->default($default)
            ->required();
    }
}
