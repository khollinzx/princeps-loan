<?php

namespace App\Traits;

use App\Abstractions\Interfaces\RepositoryInterface;
use Illuminate\Support\Facades\Log;

trait HasRepositoryTrait
{

    /*
    |--------------------------------------------------------------------------
    | Description of Use:
    |--------------------------------------------------------------------------
    |
    | This is a helper Trait which ships two methods: repo and repository.
    | repo and repository methods can be used alternatively based on convenience.
    | Either can be used statically or via instance.
    |
    | The helper assumes the namespace format of the target repository class to be:
    | App\Repositories\<Model>Repository (just as we currently have).
    |
    | But if for some reasons, the namespace is different, it can be set via REPOSITORY_CLASS as public constant on the model.
    |
    | The trait can be used on any of the database model and will automatically connect to the corresponding repository
    | of such model.
    |
    | Example of use cases:
    | $repo = App\Models\User::repo()->getAccounts();
    | $repo = App\Models\User::repository()->getAccounts();
    | $repo = (new App\Models\User())->repo()->getAccounts();
    */

    /**
     *
     * @return RepositoryInterface
     */
    public static function repo(): RepositoryInterface
    {
        $baseClass = explode('\\', get_called_class());
        $repoClass = defined(__CLASS__ . '::REPOSITORY_CLASS') ?
            constant(__CLASS__ . '::REPOSITORY_CLASS')
            :
            'App\\Repositories\\' . end($baseClass) . 'Repository';

        return (new $repoClass(new static));
    }

    /**
     *
     * @return RepositoryInterface
     */
    public static function repository(): RepositoryInterface
    {
        return static::repo();
    }
}
