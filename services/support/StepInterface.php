<?php
namespace app\services\support;

interface StepInterface
{
    public function run(Context $ctx): void;
}
