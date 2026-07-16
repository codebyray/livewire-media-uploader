<?php

namespace Codebyray\LivewireMediaUploader\Enums;

enum NameConflictStrategy: string
{
    case RENAME = 'rename';
    case REPLACE = 'replace';
    case SKIP = 'skip';
    case ALLOW = 'allow';
}
