<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2015 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Component\Resource\Repository;

use DTL\Glob\FinderInterface;
use Puli\Repository\Api\ResourceRepository;
use Puli\Repository\Api\UnsupportedLanguageException;
use Puli\Repository\Resource\Collection\ArrayResourceCollection;
use Webmozart\PathUtil\Path;
use Puli\Repository\AbstractRepository;
use Symfony\Cmf\Component\Resource\Repository\Api\EditableRepository;
use DTL\Glob\GlobHelper;

/**
 * Abstract repository for both PHPCR and PHPCR-ODM repositories.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
abstract class AbstractPhpcrRepository extends AbstractRepository implements ResourceRepository, EditableRepository
{
    /**
     * Base path from which to serve nodes / nodes.
     *
     * @var string
     */
    private $basePath;

    /**
     * @var FinderInterface
     */
    private $finder;

    /**
     * @var GlobHelper
     */
    private $globHelper;

    /**
     * @param string $basePath
     */
    public function __construct(FinderInterface $finder, $basePath = null)
    {
        $this->finder = $finder;
        $this->basePath = $basePath;
        $this->globHelper = new GlobHelper();
    }

    /**
     * {@inheritdoc}
     */
    public function hasChildren($path)
    {
        $children = $this->listChildren($path);

        return (bool) count($children);
    }

    /**
     * {@inheritdoc}
     */
    public function find($query, $language = 'glob')
    {
        if ($language != 'glob') {
            throw new UnsupportedLanguageException($language);
        }

        $nodes = $this->finder->find($this->resolvePath($query));

        return $this->buildCollection($nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($query, $language = 'glob')
    {
        $this->failUnlessGlob($language);
        $nodes = $this->finder->find($this->resolvePath($query));

        if (0 === count($nodes)) {
            return 0;
        }

        try {
            // delegate remove nodes to the implementation
            $this->removeNodes($nodes);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Error encountered when removing resource(s) using query "%s"',
                $query
            ), null, $e);
        }

        return count($nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function move($query, $targetPath, $language = 'glob')
    {
        $this->failUnlessGlob($language);
        $nodes = $this->finder->find($this->resolvePath($query));

        if (0 === count($nodes)) {
            return 0;
        }

        $targetPath = $this->resolvePath($targetPath);

        try {
            // delegate moving to the implementation
            $this->moveNodes($nodes, $query, $targetPath);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                'Error encountered when moving resource(s) using query "%s"',
                $query
            ), null, $e);
        }

        return count($nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        throw new \BadMethodCallException('Clear not supported');
    }

    /**
     * {@inheritdoc}
     */
    public function add($path, $resource)
    {
        throw new \BadMethodCallException('Add not supported');
    }

    /**
     * Return the path with the basePath prefix
     * if it has been set.
     *
     * @param string $path
     *
     * @return string
     */
    protected function resolvePath($path)
    {
        $path = $this->sanitizePath($path);

        if ($this->basePath) {
            $path = $this->basePath.$path;
        }

        $path = Path::canonicalize($path);

        return $path;
    }

    /**
     * Remove the base prefix from the given path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function unresolvePath($path)
    {
        $path = substr($path, strlen($this->basePath));

        return $path;
    }

    protected function isGlobbed($string)
    {
        return $this->globHelper->isGlobbed($string);
    }

    /**
     * Build a collection of PHPCR resources.
     *
     * @return ArrayResourceCollection
     */
    abstract protected function buildCollection(array $nodes);

    /**
     * Rmeove the given nodes.
     *
     * @param NodeInterface[]
     */
    abstract protected function removeNodes(array $nodes);

    /**
     * Move the given nodes.
     *
     * @param NodeInterface[]
     */
    abstract protected function moveNodes(array $nodes, $query, $targetPath);
}
