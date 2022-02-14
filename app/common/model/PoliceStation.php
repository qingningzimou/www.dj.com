<?php

namespace app\common\model;

class PoliceStation extends Basic
{
    protected $table = 'deg_sys_police_station';

    protected function base($query)
    {
        return $query;
    }
}