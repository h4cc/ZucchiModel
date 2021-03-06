<?php
/**
 * ZucchiModel (http://zucchi.co.uk)
 *
 * @link      http://github.com/zucchi/ZucchiModel for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zucchi Limited. (http://zucchi.co.uk)
 * @license   http://zucchi.co.uk/legals/bsd-license New BSD License
 */
namespace ZucchiModel\Model;

use Zend\Code\Annotation\AnnotationManager;
use Zend\Code\Annotation\Parser;
use Zend\Code\Reflection\ClassReflection;

use Zend\EventManager\EventManager;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;

use ZucchiModel\Adapter\AdapterInterface;
use ZucchiModel\Behaviour;
use ZucchiModel\Hydrator;
use ZucchiModel\Annotation\AnnotationListener;
use ZucchiModel\Metadata;
use ZucchiModel\Query\Criteria;
use ZucchiModel\ResultSet;

/**
 * Model Manager for ORM
 *
 * @author Matt Cockayne <matt@zucchi.co.uk>
 * @author Rick Nicol <rick@zucchi.co.uk>
 * @package ZucchiModel
 * @subpackage Model
 * @category
 */
class Manager implements EventManagerAwareInterface
{
    /**
     * Zend Db Adapter used for connecting to the database.
     *
     * @var \ZucchiModel\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * SQL object used to create SQL statements.
     *
     * @var \Zend\Db\Sql\Sql
     */
    protected $sql;

    /**
     * Event Manager.
     *
     * @var EventManager
     */
    protected $eventManager;

    /**
     * Annatation Manager.
     *
     * @var AnnotationManager;
     */
    protected $annotationManager;

    /**
     * Mapping data for loaded models.
     *
     * @var array
     */
    protected $modelMetadata = array();

    /**
     * Container of models to persist.
     *
     * @var Container
     */
    protected $modelContainer;

    /**
     * Queue of models to create.
     *
     * @var ArrayObject
     */
    protected $createQueue;

    /**
     * Queue of models to update.
     *
     * @var ArrayObject
     */
    protected $updateQueue;

    /**
     * Queue of models to remove.
     *
     * @var \ArrayObject
     */
    protected $removeQueue;

    /**
     * Collection of know Annotations related to Model Manager.
     *
     * @var array
     */
    protected $registeredAnnotations = array(
        'ZucchiModel\Annotation\Field',
        'ZucchiModel\Annotation\Relationship',
        'ZucchiModel\Annotation\Target',
    );

    /**
     * Construct Model Manager with supplied ZucchiModel Adapter.
     *
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->setAdapter($adapter);
        $this->resetQueue('create');
        $this->resetQueue('update');
        $this->resetQueue('remove');
    }

    /**
     * Reset the relevant queue.
     *
     * @param string $queue
     */
    protected function resetQueue($queue)
    {
        switch ($queue) {
            case 'create':
            case 'update':
            case 'remove':
                $this->{$queue . 'Queue'} = new \ArrayObject();;
        }
    }

    /**
     * Get ZucchiModel Adapter.
     *
     * @return \ZucchiModel\Adapter\AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Set Zend Db Adapter.
     *
     * @param \ZucchiModel\Adapter\AdapterInterface $adapter
     * @return Manager
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $adapter->setEventManager($this->getEventManager());
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get Event Manager.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (null === $this->eventManager) {
            $this->setEventManager(new EventManager());
        }
        return $this->eventManager;
    }

    /**
     * Set Event Manager.
     *
     * @param EventManagerInterface $events
     * @return $this
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $annotationListener = new AnnotationListener();
        $annotationListener->attach($events);

        $hydrationListener = new Hydrator\HydrationListener($this);
        $hydrationListener->attach($events);

        $behaviourListener = new Behaviour\BehaviourListener($this);
        $behaviourListener->attach($events);

        $this->eventManager = $events;
        return $this;
    }

    /**
     * Get Annotation Manager.
     *
     * @return \Zend\Code\Annotation\AnnotationManager
     */
    public function getAnnotationManager()
    {
        if (!$this->annotationManager) {
            $this->annotationManager = new AnnotationManager();
            $parser = new Parser\DoctrineAnnotationParser();
            foreach ($this->registeredAnnotations as $annotation) {
                $parser->registerAnnotation($annotation);
            }
            $this->annotationManager->attach($parser);
        }
        return $this->annotationManager;
    }

    /**
     * Set Annotation Manager.
     *
     * @param \Zend\Code\Annotation\AnnotationManager $annotationManager
     */
    public function setAnnotationManager(AnnotationManager $annotationManager)
    {
        $this->annotationManager = $annotationManager;
    }

    /**
     * Get Metadata for a specified given class name.
     *
     * @param string $class
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function getMetadata($class)
    {
        // Class name must be a string
        if (!is_string($class)) {
            throw new \InvalidArgumentException(sprintf('Class must be a string. Given: %s.', var_export($class, true)));
        }

        if (!array_key_exists($class, $this->modelMetadata)) {
            // Add class to cache
            $this->modelMetadata[$class] = $md = new Metadata\MetaDataContainer();

            // Get the Model's Annotations
            $reflection  = new ClassReflection($class);
            $am = $this->getAnnotationManager();
            $em = $this->getEventManager();

            // Find all the Model Metadata
            if ($annotations = $reflection->getAnnotations($am)) {
                $event = new Event();
                $event->setName('prepareModelMetadata');
                $event->setTarget($annotations);
                $event->setParam('model', $md->getModel());
                $event->setParam('relationships', $md->getRelationships());
                $em->trigger($event);
            }

            // Find all the Fields Metadata
            if ($properties = $reflection->getProperties()) {
                $event = new Event();
                $event->setName('prepareFieldMetadata');
                $event->setTarget($md->getFields());
                foreach ($properties as $property) {
                    if ($annotation = $property->getAnnotations($am)) {
                        $event->setParam('property', $property->getName());
                        $event->setParam('annotation', $annotation);
                        $em->trigger($event);
                    }
                }
            }

            // Check for Data Sources and get their Table Name
            if ($target = $md->getModel()->getTarget()) {
                $md->setAdapter($this->getAdapter()->getMetaData($target));
            }
        }

        return $this->modelMetadata[$class];
    }

    /**
     * Get given Relationships.
     *
     * @param $relationship
     * @param $model
     * @param int $paginatedPageSize
     * @return bool|ResultSet\HydratingResultSet|ResultSet\PaginatedResultSet false if nothing can be found
     */
    public function getRelationship($relationship, $model, $paginatedPageSize = 0)
    {
        // Check relationship is a string.
        if (!is_string($relationship) || empty($relationship)) {
            throw new \InvalidArgumentException(sprintf('Relationship must be a non empty string. Given: %s', var_export($relationship, true)));
        }

        // Check model is an object.
        if (!is_object($model)) {
            throw new \InvalidArgumentException(sprintf('Models must be an object. Given: %s', var_export($model, true)));
        }

        // Check paginatedPageSize is an integer.
        if (!is_int($paginatedPageSize) || $paginatedPageSize < 0) {
            throw new \InvalidArgumentException(sprintf('Paginated Page Size must be a positive integer. Given: %s', var_export($paginatedPageSize, true)));
        }

        $metadata = $this->getMetadata(get_class($model));
        $relationship = $metadata->getRelationships()->getRelationship($relationship);

        // Create Criteria for the find.
        $criteria = new Criteria(array(
            'model' => $relationship['model'],
        ));
        // Add relationship details to criteria.
        $criteria = $metadata->getAdapter()->addRelationship(
            $model,
            $criteria,
            $relationship
        );

        // Find related models by join type.
        switch ($relationship['type']) {
            case 'toOne':
                return $this->findOne($criteria);
                break;
            case 'toMany':
            case 'ManytoMany':
                return $this->findAll($criteria, $paginatedPageSize);
                break;
        }

        // Nothing found.
        return false;
    }

    /**
     * Find and return a single model.
     *
     * @param Criteria $criteria
     * @return bool
     * @throws \RuntimeException
     * @todo: take into account schema and table names in foreignKeys
     * @todo: store results in mapCache
     * @todo: add listener for converting currency etc.
     */
    public function findOne(Criteria $criteria)
    {
        // Get model and check it exists
        $model = $criteria->getModel();
        if (!class_exists($model)) {
            throw new \RuntimeException(sprintf('Model does not exist. Given: %s', var_export($model, true)));
        }

        // Get metadata for the given model
        $metadata = $this->getMetadata($model);

        // Check dataSource and metadata exist
        if (!$metadata->getAdapter()) {
            throw new \RuntimeException(sprintf('No Adapter Specific Metadata can be found for this Model. Given: %s', var_export($model, true)));
        }

        $criteria->setLimit(1);

        $query = $this->getAdapter()->buildQuery($criteria, $metadata);

        $results = $this->getAdapter()->execute($query);

        // Check for single result
        if (!$result = $results->current()) {
            // return false as no result found
            return false;
        }

        $model = new $model();

        // Trigger Hydration events
        $event = new Event('preHydrate', $model, array('data' => $result));
        $this->getEventManager()->trigger($event);

        $event->setName('hydrate');
        $this->getEventManager()->trigger($event);

        $event->setName('postHydrate');
        $this->getEventManager()->trigger($event);
        
        // Return result
        return $model;
    }

    /**
     * Find and return a collection of models.
     *
     * @param Criteria $criteria
     * @param int $paginatedPageSize if greater than 1 then paginate results using this as Page Size
     * @return bool|ResultSet\HydratingResultSet|ResultSet\PaginatedResultSet
     * @throws \RuntimeException
     */
    public function findAll(Criteria $criteria, $paginatedPageSize = 0)
    {
        // Get model and check it exists
        $model = $criteria->getModel();

        if (!class_exists($model)) {
            throw new \RuntimeException(sprintf('Model does not exist. Given %s', var_export($model, true)));
        }

        // Get metadata for the given model
        $metadata = $this->getMetadata($model);

        // Check dataSource and metadata exist
        if (!$metadata->getAdapter()) {
            throw new \RuntimeException(sprintf('No Adapter Specific Metadata can be found for this Model. Given: %s', var_export($model, true)));
        }

        // Check if a Paginated Result Set is wanted,
        // else return standard Hydrating Result Set
        if ($paginatedPageSize > 0) {
            $resultSet = new ResultSet\PaginatedResultSet($this, $criteria, $paginatedPageSize);
        } else {
            $resultSet = $this->getAdapter()->find($criteria, $metadata);
        }

        return $resultSet;
    }

    /**
     * Return row count.
     *
     * @param Criteria $criteria
     * @return int|bool
     * @throws \RuntimeException
     */
    public function countAll(Criteria $criteria)
    {
        // Get model and check it exists
        $model = $criteria->getModel();

        if (!class_exists($model)) {
            throw new \RuntimeException(sprintf('Model does not exist. Given: %s', var_export($model, true)));
        }

        // Get metadata for the given model
        $metadata = $this->getMetadata($model);

        // Check dataSource and metadata exist
        if (!$metadata->getAdapter()) {
            throw new \RuntimeException(sprintf('No Adapter Specific Metadata can be found for this Model. Given: %s', var_export($model, true)));
        }

        // Force limit and offset to null
        $criteria->setLimit(null);
        $criteria->setOffset(null);

        $query = $this->getAdapter()->buildCountQuery($criteria, $metadata);

        $result = $this->getAdapter()->execute($query);

        if ($count = $result->current()) {
            return $count['count'];
        } else {
            return false;
        }
    }

    /**
     * Add model to modelContainer for later writing to datasource.
     *
     * @param $model
     * @param array $related
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function persist($model, $related = array())
    {
        // Check model is an object.
        if (!is_object($model)) {
            throw new \InvalidArgumentException(sprintf('Models must be an object. Given: %s', var_export($model, true)));
        }

        // Check $related is an array or Traversable.
        if (!is_array($related) && !($related instanceof \Traversable)) {
            throw new \InvalidArgumentException(sprintf('Related models must be an array or Traversable. Given: %s', var_export($related, true)));
        }

        // Create new container if not yet set.
        if (!$this->modelContainer) {
            $this->modelContainer = new Container($this);
        }

        $metadata = $this->getMetadata(get_class($model));

        // Build up a list of related model hashes.
        $modelRelations = array();

        foreach ($related as $name => $relations) {
            // Check this is a relationship on the given model.
            if (!$relationship = $metadata->getRelationship($name)) {
                throw new \UnexpectedValueException(sprintf('Invalid relationship of "%s" defined to persist.', $name));
            }

            // Attach each related model to model container, if not
            // already in modelContainer. Build list of hashes for
            // related models.
            foreach ($relations as $relation) {
                if (!$this->modelContainer->contains($relation)) {
                    $this->modelContainer->attach($relation, array(
                        'metadata' => $this->getMetadata($relationship['model']),
                        'relationships' => array(),
                    ));
                }
                $hash = $this->modelContainer->getHash($relation);
                $modelRelations[$name][] = $hash;
            }
        }

        // Attach this model to modelContainer.
        $hash = $this->modelContainer->getHash($model);

        // If it does not exists in modelContainer, attach with
        // related model hashes. Else merged related model hashes.
        if (!$this->modelContainer->contains($model)) {
            $this->modelContainer->attach($model, array(
                'metadata' => $metadata,
                'relationships' => $modelRelations,
            ));
        } else {
            $originalMeta = $this->modelContainer->offsetGet($model);
            $this->modelContainer->offsetSet($model, array(
                'metadata' => $metadata,
                'relationships' => array_merge($originalMeta['relationships'], $modelRelations),
            ));
        }

        // If hash contains fully qualified name, it is an
        // update. Else create. See Model\Container->getHash().
        if (false !== strpos($hash, get_class($model))) {
            $this->updateQueue[$hash] = true;
        } else {
            $this->createQueue[$hash] = true;
        }
    }

    /**
     * Write persisted models to dataSource.
     */
    public function write()
    {
        if ($this->modelContainer instanceof Container) {
            $this->getAdapter()->write($this->modelContainer);
        }

        // Clean out Model Container and Queues.
        unset($this->modelContainer, $this->createQueue, $this->updateQueue, $this->removeQueue);
        $this->modelContainer = $this->createQueue = $this->updateQueue = $this->removeQueue = null;
    }
}
