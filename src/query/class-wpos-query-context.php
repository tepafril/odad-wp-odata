<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Query_Context {
    public ?string $filter             = null;   // raw $filter string
    public ?array  $select             = null;   // parsed property names
    public ?array  $orderby            = null;   // [ ['property'=>'Title', 'dir'=>'asc'], ... ]
    public int     $top                = 100;
    public int     $skip               = 0;
    public bool    $count              = false;
    public ?string $expand             = null;   // raw $expand string
    public ?string $search             = null;   // raw $search string
    public ?string $compute            = null;   // raw $compute string
    public ?string $filter_sql         = null;   // compiled SQL WHERE (set by compiler)
    public array   $filter_params      = [];     // compiled params for $wpdb->prepare()
    public array   $extra_conditions   = [];     // additional WHERE fragments (row-level security)
}
