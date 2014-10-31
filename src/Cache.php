<?php

namespace Sokil\Mongo;

class Cache implements \Countable
{
    const FIELD_NAME_VALUE = 'v';
    const FIELD_NAME_EXPIRED = 'e';
    const FIELD_NAME_TAGS = 't';
    
    private $collection;
    
    public function __construct(Database $database, $collectionName)
    {
        $this->collection = $database
            ->map($collectionName, array(
                'index' => array(
                    // date field
                    array(
                        'keys' => self::FIELD_NAME_EXPIRED,
                        'expireAfterSeconds' => 0
                    ),
                )
            ))
            ->getCollection($collectionName);
    }
    
    public function init()
    {
        $this->collection->initIndexes();
        return $this;
    }
    
    public function clear()
    {
        $this->collection->delete();
        return $this;
    }
    
    /**
     * Set with expiration on concrete date
     * 
     * @param int|string $key
     * @param mixed $value
     * @param int $timestamp
     */
    public function setDueDate($key, $value, $timestamp, array $tags = null)
    {
        $document = $this->collection
            ->createDocument(array(
                '_id' => $key,
                self::FIELD_NAME_VALUE => $value,
            ));
        
        if($timestamp) {
            $document->set(self::FIELD_NAME_EXPIRED, new \MongoDate((int) $timestamp));
        }
        
        if($tags) {
            $document->set(self::FIELD_NAME_TAGS, $tags);
        }
        
        $document->save();
        
        return $this;
    }
    
    /**
     * Set key that never expired
     * 
     * @param int|string $key
     * @param mixed $value 
     * @return \Sokil\Mongo\Cache
     */
    public function setNeverExpired($key, $value, array $tags = null)
    {
        $this->setDueDate($key, $value, null, $tags);
        
        return $this;
    }
    
    /**
     * Set with expiration in seconds
     * 
     * @param int|string $key
     * @param mixed $value
     * @param int $ttl
     */
    public function set($key, $value, $ttl, array $tags = null)
    {
        $this->setDueDate($key, $value, time() + $ttl, $tags);
        
        return $this;
    }
    
    public function get($key)
    {
        return $this->collection
            ->getDocument($key)
            ->get(self::FIELD_NAME_VALUE);
    }
    
    public function delete($key)
    {
        $this->collection->deleteDocuments(array(
            '_id' => $key,
        ));
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     */
    public function deleteMatchingTag($tag)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tag) {
            return $e->where(self::FIELD_NAME_TAGS, $tag);
        });
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     */
    public function deleteNotMatchingTag($tag)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tag) {
            return $e->whereNotEqual(self::FIELD_NAME_TAGS, $tag);
        });
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     * Document deletes if it containds all passed tags
     */
    public function deleteMatchingAllTags(array $tags)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tags) {
            return $e->whereAll(self::FIELD_NAME_TAGS, $tags);
        });
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     * Document deletes if it containds all passed tags
     */
    public function deleteByTagNotMatchingAllTags(array $tags)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tags) {
            return $e->whereNoneOf(self::FIELD_NAME_TAGS, $tags);
        });
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     * Document deletes if it containds any of passed tags
     */
    public function deleteMatchingAnyTag(array $tags)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tags) {
            return $e->whereIn(self::FIELD_NAME_TAGS, $tags);
        });
        
        return $this;
    }
    
    /**
     * Delete documents by tag
     * Document deletes if it containds any of passed tags
     */
    public function deleteNotMatchingAnyTag(array $tags)
    {
        $this->collection->deleteDocuments(function(\Sokil\Mongo\Expression $e) use($tags) {
            return $e->whereNotIn(self::FIELD_NAME_TAGS, $tags);
        });
        
        return $this;
    }
    
    public function count()
    {
        return $this->collection->count();
    }
    
    public function has($key)
    {
        return (bool) $this->get($key);
    }
    
}