<?php

  namespace DVDoug\BoxPacker;

  require_once(__DIR__ . '/../Box.php');
  require_once(__DIR__ . '/../BoxPacker.php');
  require_once(__DIR__ . '/../BoxPackingGap.php');
  require_once(__DIR__ . '/../BoxPackingLayer.php');
  require_once(__DIR__ . '/../Item.php');
  require_once(__DIR__ . '/../ItemList.php');

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

   public function getMaxWeight() {
     return $this->maxWeight;
   }
 }

 class TestItem implements Item {

   public function __construct($aDescription,$aWidth,$aLength,$aDepth,$aWeight) {
     $this->description = $aDescription;
     $this->width = $aWidth;
     $this->length = $aLength;
     $this->depth = $aDepth;
     $this->weight = $aWeight;
   }

   public function getDescription() {
     return $this->description;
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
     return $this->width * $this->length * $this->depth;
   }
 }

