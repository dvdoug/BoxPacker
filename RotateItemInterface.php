<?php

namespace DVDoug\BoxPacker;

interface RotateItemInterface extends Item{

    /**
     * Whether to allow vertical rotation
     * @return boolean
     */
    public function isRotateVertical();
}