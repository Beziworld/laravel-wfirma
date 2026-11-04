<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\WFirma')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\WFirma\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\WFirma\Exceptions')
    ->toExtend('Beziworld\WFirma\Exceptions\WFirmaException')
    ->ignoring('Beziworld\WFirma\Exceptions\WFirmaException');
