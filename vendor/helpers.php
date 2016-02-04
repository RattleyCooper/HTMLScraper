<?php

use Symfony\Component\VarDumper\VarDumper;

if ( ! function_exists('dump') )
{
    function dump($var)
    {
        return (new VarDumper())->dump($var);
    }
}

if ( ! function_exists('dd') )
{
    function dd($var)
    {
        (new VarDumper())->dump($var);
        die(1);
    }
}