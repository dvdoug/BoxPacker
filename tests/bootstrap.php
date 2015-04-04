<?php
  /**
   * Box packing (3D bin packing, knapsack problem)
   * @package BoxPacker
   * @author Doug Wright
   */

  namespace DVDoug\BoxPacker;

  class TestBox implements Box {

    public function __construct($aReference, $aOuterWidth,$aOuterLength,$aOuterDepth,$aEmptyWeight,$aInnerWidth,$aInnerLength,$aInnerDepth,$aMaxWeight) {
      $this->reference = $aReference;
      $this->outerWidth = $aOuterWidth;
      $this->outerLength = $aOuterLength;
      $this->outerDepth = $aOuterDepth;
      $this->emptyWeight = $aEmptyWeight;
      $this->innerWidth = $aInnerWidth;
      $this->innerLength = $aInnerLength;
      $this->innerDepth = $aInnerDepth;
      $this->maxWeight = $aMaxWeight;
      $this->innerVolume = $this->innerWidth * $this->innerLength * $this->innerDepth;
    }

    public function getReference() {
      return $this->reference;
    }

    public function getOuterWidth() {
      return $this->outerWidth;
    }

    public function getOuterLength() {
      return $this->outerLength;
    }

    public function getOuterDepth() {
      return $this->outerDepth;
    }

    public function getEmptyWeight() {
      return $this->emptyWeight;
    }

    public function getInnerWidth() {
      return $this->innerWidth;
    }

    public function getInnerLength() {
      return $this->innerLength;
    }

    public function getInnerDepth() {
      return $this->innerDepth;
    }

    public function getInnerVolume() {
      return $this->innerVolume;
    }

    public function getMaxWeight() {
      return $this->maxWeight;
    }
  }

  class TestItem implements Item {

    public function __construct($aName,$aWidth,$aLength,$aDepth,$aWeight) {
      $this->name = $aName;
      $this->width = $aWidth;
      $this->length = $aLength;
      $this->depth = $aDepth;
      $this->weight = $aWeight;
      $this->volume = $this->width * $this->length * $this->depth;
    }

    public function getName() {
      return $this->name;
    }

    public function getWidth() {
      return $this->width;
    }

    public function getLength() {
      return $this->length;
    }

    public function getDepth() {
      return $this->depth;
    }

    public function getWeight() {
      return $this->weight;
    }

    public function getVolume() {
      return $this->volume;
    }
 }

