<?php

namespace CQL\Lexer\TokenType;

use CQL\Lexer\TokenType\Traits\TokenEnum;

/**
 * Enum class for CQL keywords.
 *
 * @package CQL\Lexer\Enum
 */
enum Keyword: string
{
    use TokenEnum;

    case SELECT = 'SELECT';
    case FROM = 'FROM';
    case WHERE = 'WHERE';
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
    case DEFINE = 'DEFINE';
    case AS = 'AS';
    case COLUMNS = 'COLUMNS';
    case HEADERS = 'HEADERS';
    case WITH = 'WITH';
    case WITHOUT = 'WITHOUT';
    case ASC = 'ASC';
    case DESC = 'DESC';
    case DISTINCT = 'DISTINCT';
    case LIMIT = 'LIMIT';
    case OFFSET = 'OFFSET';
    case JOIN = 'JOIN';
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case ON = 'ON';
    case EXISTS = 'EXISTS';
    case GROUP = 'GROUP';
    case BY = 'BY';
    
    // Aggregate functions
    case COUNT = 'COUNT';
    case SUM = 'SUM';
    case AVG = 'AVG';
    case MIN = 'MIN';
    case MAX = 'MAX';
    
    // Date functions
    case DATE = 'DATE';
    case YEAR = 'YEAR';
    case MONTH = 'MONTH';
    case DAY = 'DAY';

    // Write operations (DML)
    case INSERT = 'INSERT';
    case INTO = 'INTO';
    case VALUES = 'VALUES';
    case UPDATE = 'UPDATE';
    case SET = 'SET';
    case DELETE = 'DELETE';

//    TODO: Future implementation (DDL)
//    case CREATE = 'CREATE';
//    case TABLE = 'TABLE';
//    case DROP = 'DROP';
}