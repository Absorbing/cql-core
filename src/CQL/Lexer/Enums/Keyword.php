<?php

namespace CQL\Lexer\Enums;

use CQL\Lexer\Traits\TokenEnumTrait;

/**
 * Enum class for CQL keywords.
 *
 * @package CQL\Lexer\Enums
 */
enum Keyword: string
{
    use TokenEnumTrait;

    case SELECT = 'SELECT';
    case FROM = 'FROM';
    case WHERE = 'WHERE';
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
    case DEFINE = 'DEFINE';
    case ASC = 'ASC';
    case DESC = 'DESC';
    case LIMIT = 'LIMIT';
    case OFFSET = 'OFFSET';
    case JOIN = 'JOIN';
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case ON = 'ON';

//    TODO: Future implementation
//    case INSERT = 'INSERT';
//    case INTO = 'INTO';
//    case VALUES = 'VALUES';
//    case UPDATE = 'UPDATE';
//    case SET = 'SET';
//    case DELETE = 'DELETE';
//    case CREATE = 'CREATE';
//    case TABLE = 'TABLE';
//    case DROP = 'DROP';
}