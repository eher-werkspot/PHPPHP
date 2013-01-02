<?php

namespace PHPPHP\Engine\OpLines;

class CastBool extends \PHPPHP\Engine\OpLine {

    public function execute(\PHPPHP\Engine\ExecuteData $data) {
        $this->result->setValue($this->op1->toBool());

        $data->nextOp();
    }

}