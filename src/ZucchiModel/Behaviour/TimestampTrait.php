<?php
/**
 * ZucchiModel (http://zucchi.co.uk)
 *
 * @link      http://github.com/zucchi/ZucchiModel for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zucchi Limited. (http://zucchi.co.uk)
 * @license   http://zucchi.co.uk/legals/bsd-license New BSD License
 */
namespace ZucchiModel\Behaviour;

use ZucchiModel\Annotation as Model;

/**
 * Timestamp Behaviour Trait
 *
 * @author Matt Cockayne <matt@zucchi.co.uk>
 * @author Rick Nicol <rick@zucchi.co.uk>
 * @package ZucchiModel
 * @subpackage Behaviour
 * @category
 */
trait TimestampTrait
{
    /**
     * @var datetime;
     * @Model\Field("datetime")
     */
    public $createdAt;

    /**
     * @var datetime
     * @Model\Field("datetime")
     */
    public $updatedAt;
}