# Auto Join Eloquent

**auto-join-eloquent** is a Laravel package that automates the process of joining related Eloquent models in your queries. By leveraging relationship metadata and centralized alias management, the package simplifies complex query building and ensures consistent `JOIN` clauses throughout your application.

This package was inspired by the need for dynamic queries in osTicket’s custom forms and fields. It was developed to address the challenges of handling dynamic and nested auto `JOIN` operations in such environments.

## Features

- **Automatic Join Processing**: Automatically joins related models based on defined Eloquent relationships without requiring manual `JOIN` clauses.
- **Nested Relationships**: Supports deep nesting of relationships using dot (`.`) or double-underscore (`__`) notation.
- **Aggregate Functions**: Seamlessly compile aggregates in `SELECT` and `HAVING` clauses with built-in support for functions such as `COUNT`, `SUM`, `AVG`, `MIN`, and `MAX`.

### Aliasing Management

- **Custom Aliases**: Define custom join aliases directly in your models via a `$joinAliases` property.
- **Auto Generated Aliasing**: Optionally, the package can automatically generate simple sequential aliases (`A`, `B`, `C`, …, then `A1`, `B1`, etc.) or descriptive aliases based on relationship keys.

- **Raw SQL Handling**: Supports raw `HAVING` clauses and can intelligently compile aggregate expressions when raw SQL references relationships.

### Clause-Specific Compilation

- **SelectCompiler**: Processes `SELECT` clause columns (adds aliases and handles aggregates).
- **WhereCompiler**: Transforms `WHERE` clause columns into fully qualified column names (without aliasing).
- **HavingCompiler**: Compiles `HAVING` clause expressions (handles aggregates without aliasing).
- **OrderByCompiler**: Compiles `ORDER BY` clause expressions, ensuring that the sorting columns are properly resolved.
- **GroupByCompiler**: Compiles `GROUP BY` clause expressions, guaranteeing that grouping columns are correctly qualified with their respective aliases.

## Installation

Install via Composer:

```bash
composer require protich/auto-join-eloquent
```

## Usage

### Enabling Auto Join

Include the `AutoJoinTrait` in your Eloquent models to enable auto-join functionality. For example, in your `User` model:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
class User extends Model {
    use AutoJoinTrait;

    // Define relationships normally.
    public function agent() {
        return $this->hasOne(Agent::class, 'user_id');
    }
}
```

### Custom Join Aliases

To define custom join aliases, add a `$joinAliases` property to your model. For example, create a `UserStaff` model to force the agent relationship to use the alias "staff":

```php
<?php
namespace App\Models;
use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
use Illuminate\Database\Eloquent\Model;
class UserStaff extends User {
    protected $table = 'users';
    public $timestamps = false;

    // Define a custom alias for the "agent" relationship.
    public $joinAliases = [
        'agent' => 'staff'
    ];

    public function agent() {
        return $this->hasOne(Agent::class, 'user_id');
    }
}
```

### Query Examples

#### Basic Auto-Joined Query

auto-join-eloquent allows you to write clean queries that automatically apply joins based on relationship notation. For example, a basic query with an aggregate and a WHERE condition might look like:

```php
<?php
$results = User::query()
    ->select([
        'name as user_name',
        'agent.id as agent_id',
        DB::raw('COUNT(agent.departments.id) as dept_count')
    ])
    ->where('agent.id', '=', 1)
    ->groupBy('agent.id')
    ->havingRaw('COUNT(agent.departments.id) > ?', [0])
    ->orderBy('name', 'asc')
    ->get();
```

In this example, the package automatically joins the related `agent` model and aggregates data from the nested `departments` relationship. The HAVING clause compiles the raw SQL aggregate as needed, ensuring proper filtering. Additionally, ORDER BY and GROUP BY clauses are compiled to ensure proper aliasing and relationship resolution.

#### Advanced Raw HAVING Example

You can also use raw expressions for HAVING clauses that reference relationships. For instance:

```php
<?php
$results = User::query()
    ->select(['name as user_name'])
    ->groupBy('agent.id')
    ->havingRaw('COUNT(agent.departments.id) > ?', [2])
    ->get();
```

This query compiles the raw SQL and correctly applies the aggregate condition.

## Configuration

You can configure default behavior via the package configuration file (if published):

- **use_simple_aliases:** Enable or disable simple sequential alias generation.
- **join_type:** Set the default join type (e.g., left or inner).

## Internal Architecture

- **AutoJoinQueryBuilder:**
  Extends Laravel's Eloquent Builder to intercept queries and apply auto-join processing. It delegates alias resolution to a dedicated alias manager.

- **JoinAliasManager:**
  Centralizes join alias resolution by generating sequential aliases (or using forced custom aliases defined in models) and preventing collisions. All alias mapping logic is managed in this component.

- **Join Helpers:**
  Classes such as `JoinClauseInfo` and `JoinContext` encapsulate join clause information and context for building JOIN statements.

- **Compilers:**
  Components like `HavingCompiler`, `OrderByCompiler`, and `GroupByCompiler` compile various parts of the query by interpreting relationship chains and applying alias logic.

- **AutoJoinTrait:**
  A trait you include in your models to enable auto-join behavior and hook into the query builder.

## Known Limitations & TODOs

- **Manual vs. Auto Joins:** The package assumes that auto-join is the primary mechanism for adding JOIN clauses. Future versions may reconcile manual JOIN clauses with auto-joins.
- **Raw SQL in HAVING Clauses:** While the package supports raw HAVING expressions, further enhancements may be needed to robustly compile complex raw SQL.
- **Alias Collision Handling:** Currently, if a forced custom alias is already in use, the system falls back to auto-generation. Future enhancements might include throwing an exception or logging warnings.
- **Join Reconciliation:** Reconciling manually added joins with auto-joins is a potential future enhancement.

## Contributing

Contributions are welcome! Please fork the repository and submit pull requests. Follow the coding standards and include tests for any new features or bug fixes.

## License

This package is open-source software licensed under the MIT License.

For more details, visit the project repository:
[https://github.com/protich/auto-join-eloquent](https://github.com/protich/auto-join-eloquent)

