<?php

namespace App\Enums;

enum FormFieldType: string
{
    case Text = 'TEXT';
    case Textarea = 'TEXTAREA';
    case Number = 'NUMBER';
    case Date = 'DATE';
    case Select = 'SELECT';
    case Multiselect = 'MULTISELECT';
    case File = 'FILE';
    case Checkbox = 'CHECKBOX';
}
