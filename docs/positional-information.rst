.. _positional_information:

Positional information
======================

It is possible to see the precise positional and dimensional information of each item as packed. This is exposed as x,y,z
co-ordinates from origin, alongside width/length/depth in the packed orientation. ::

           z    y
           |   /
           |  /
           | /
           |/
           0------x

Example:

.. code-block:: php

    <?php

        // assuming packing already took place
        foreach ($packedBoxes as $packedBox) {
            $packedItems = $packedBox->getItems();
            foreach ($packedItems as $packedItem) { // $packedItem->getItem() is your own item object
                echo $packedItem->getItem()->getDescription() .  ' was packed at co-ordinate ' ;
                echo '(' . $packedItem->getX() . ', ' . $packedItem->getY() . ', ' . $packedItem->getZ() . ') with ';
                echo 'l' . $packedItem->getLength() . ', w' . $packedItem->getWidth() . ', d' . $packedItem->getDepth();
                echo PHP_EOL;
            }
        }
