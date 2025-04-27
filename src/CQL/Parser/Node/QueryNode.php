<?php

namespace CQL\Parser\Node;

class QueryNode
{
    /**
     * @var DefineNode
     */
    public DefineNode $define;

    /**
     * @var SelectNode
     */
    public SelectNode $select;

    /**
     * @var FromNode
     */
    public FromNode $from;

    /**
     * @var WhereNode|null
     */
    public ?WhereNode $where;

    /**
     * Create a new QueryNode instance.
     *
     * @param DefineNode $define
     * @param SelectNode $select
     * @param FromNode $from
     * @param WhereNode|null $where
     */
    public function __construct(DefineNode $define, SelectNode $select, FromNode $from, ?WhereNode $where = null)
    {
        $this->define = $define;
        $this->select = $select;
        $this->from = $from;
        $this->where = $where;
    }
}