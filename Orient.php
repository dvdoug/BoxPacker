<?php
/**
 * Box|Item orientation optimizer
 * @package BoxPacker
 * @author Kevin Farley / eCommunities
 */
  namespace DVDoug\BoxPacker;

  /**
   * Orient Static Class
   * @author Kevin Farley / eCommunities
   * @package BoxPacker
   */
  class Orient {
    
    /**
     * Optimize an box's orientation
     * @static 
     * @param box $box
     * @return box
     */
    public static function box(box $box) {
    	// Ensure the proper methods exist for orientation reset, otherwise skip it.
    	// FIXME: This can be eliminated in the future once the box interface is updated to include these methods (NB: which would break backward compatibility)
    	if (!method_exists($box,'setInnerWidth') || !method_exists($box,'setInnerLength') || !method_exists($box,'setInnerDepth') || !method_exists($box,'setOuterWidth') || !method_exists($box,'setOuterLength') || !method_exists($box,'setOuterDepth')) {
    		return $box;
    	}
    	
    	// Extract the basic dimensions, break early if already oriented optimally
    	if ($box->getOuterLength() <= $box->getOuterWidth() && $box->getOuterWidth() <= $box->getOuterDepth()) { 
    		return $box;
    	} else {
    		$outer = array($box->getOuterLength(), $box->getOuterWidth(), $box->getOuterDepth());
    		$inner = array($box->getInnerLength(), $box->getInnerWidth(), $box->getInnerDepth());
    	}
    		
    	// Orient the box so that the longest edge is placed in depth, and then the smallest in length
    	array_multisort($outer, $inner);
    		
    	// Reset the box dimensions
    	$box->setOuterLength($outer[0]);
    	$box->setOuterWidth($outer[1]);
    	$box->setOuterDepth($outer[2]);
    		
    	$box->setInnerLength($inner[0]);
    	$box->setInnerWidth($inner[1]);
    	$box->setInnerDepth($inner[2]);

    	return $box;
    }
    
    /**
     * Optimize an item's orientation 
     * @static
     * @param item $item
     * @return item
     */
    public static function item($item) {
    	// Ensure the proper methods exist for orientation reset, otherwise skip it.
    	// FIXME: This can be eliminated in the future once the item interface is updated to include these methods (NB: which would break backward compatibility)
    	if (!method_exists($item,'setWidth') || !method_exists($item,'setLength') || !method_exists($item,'setDepth')) {
    		return $item;
    	}
    	
    	// Extract the basic dimensions, break early if already oriented optimally
    	if ($item->getLength() <= $item->getWidth() && $item->getWidth() <= $item->getDepth()) { 
    		return $item;
    	} else {
    		$measures = array($item->getLength(), $item->getWidth(), $item->getDepth());
    	}
    	
    	// Orient the item so that the longest edge is placed in depth, and then the smallest in length
    	sort($measures);
    	
    	// Reset the item dimensions
    	$item->setLength($measures[0]);
    	$item->setWidth($measures[1]);
    	$item->setDepth($measures[2]);
    	
    	return $item;
    }
    
  }
