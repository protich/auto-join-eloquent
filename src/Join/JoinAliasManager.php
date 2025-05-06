<?php

namespace protich\AutoJoinEloquent\Join;

use Illuminate\Database\Eloquent\Model;

class JoinAliasManager
{
    /**
     * Mapping of keys (e.g. relationship chain or table name) to join aliases.
     *
     * @var array<string, string>
     */
    protected array $aliasMap = [];

    /**
     * Counter for generating sequential aliases.
     *
     * @var int
     */
    protected int $aliasCounter = 0;

    /**
     * Whether to use simple sequential alias generation.
     *
     * @var bool
     */
    protected bool $useSimpleAliases = false;

    /**
     * Constructor.
     *
     * @param bool $useSimpleAliases Whether to use simple sequential aliases.
     */
    public function __construct(bool $useSimpleAliases = false)
    {
        $this->useSimpleAliases = $useSimpleAliases;
    }

    /**
     * Set whether to use simple sequential aliases.
     *
     * @param bool $useSimple
     * @return void
     */
    public function setUseSimpleAliases(bool $useSimple): void
    {
        $this->useSimpleAliases = $useSimple;
    }

    /**
     * Get or resolve the alias for a given key.
     *
     * This method checks if an alias is already mapped for the provided key.
     * If not, it generates a new alias. When simple alias generation is enabled,
     * it uses sequential aliases (A, B, C, …, then A1, B1, etc.), ensuring no duplicates.
     * Otherwise, it uses the provided default or falls back to the key itself.
     * The resolved alias is stored in the alias map and returned.
     *
     * @param string      $key     The mapping key (e.g. a relationship chain or table name).
     * @param string|null $default Optional default alias if none is set.
     * @return string The resolved alias.
     */
    public function getAlias(string $key, ?string $default = null): string
    {
        if (!isset($this->aliasMap[$key])) {
            if ($this->useSimpleAliases) {
                do {
                    if ($this->aliasCounter < 26) {
                        $alias = chr(65 + $this->aliasCounter);
                    } else {
                        $letter = chr(65 + ($this->aliasCounter % 26));
                        $number = intdiv($this->aliasCounter, 26);
                        $alias = $letter . $number;
                    }
                    $this->aliasCounter++;
                } while (in_array($alias, $this->aliasMap, true));
                $this->aliasMap[$key] = $alias;
            } else {
                $this->aliasMap[$key] = $default ?? $key;
            }
        }
        return $this->aliasMap[$key];
    }

    /**
     * Set the alias for a given key.
     *
     * @param string $key   The mapping key (e.g. a relationship chain or table name).
     * @param string $alias The alias to assign.
     * @return void
     */
    public function setAlias(string $key, string $alias): void
    {
        $this->aliasMap[$key] = $alias;
    }

    /**
     * Resolve the model alias for a given relationship chain.
     *
     * This method checks if the given model defines a forced custom alias for the
     * relationship via a property such as $joinAliases. If a custom alias is found,
     * and if it is not already in use, it is stored and returned.
     *
     * TODO: Consider throwing an exception if the forced custom alias is already in use
     * for a different key.
     *
     * @param Model       $model     The model instance.
     * @param string      $chainKey  The normalized relationship chain.
     * @param string|null $default   Optional default alias if none is set.
     * @return string The resolved alias.
     */
    public function resolveModelAlias(Model $model, string $chainKey, ?string $default = null): string
    {
        if (property_exists($model, 'joinAliases') 
            && isset($model->joinAliases[$chainKey]) // @phpstan-ignore-line
            && ($customAlias = $model->joinAliases[$chainKey])
            && !in_array($customAlias, $this->aliasMap, true)) {
            /** @var string $customAlias */
            $this->setAlias($chainKey, $customAlias);
        }
        return $this->getAlias($chainKey, $default);
    }
}
