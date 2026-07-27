<?php

namespace protich\AutoJoinEloquent\Compilers;

/**
 * HavingCompiler
 *
 * Compiles HAVING clause expressions. Inherits relationship-aware logic,
 * COALESCE/aggregate support, and alias handling from BaseCompiler.
 */
class HavingCompiler extends BaseCompiler
{
    /**
     * {@inheritDoc}
     */
    protected const BINDING_TYPE = 'having';

    // No overrides necessary
}
