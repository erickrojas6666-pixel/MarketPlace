namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de roles y permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear Permisos Básicos
        Permission::create(['name' => 'publicar servicios']);
        Permission::create(['name' => 'contratar servicios']);
        Permission::create(['name' => 'gestionar plataforma']);

        // 2. Crear Roles y Asignar Permisos
        $roleCliente = Role::create(['name' => 'cliente']);
        $roleCliente->givePermissionTo('contratar servicios');

        $rolePrestador = Role::create(['name' => 'prestador']);
        $rolePrestador->givePermissionTo(['publicar servicios', 'contratar servicios']);

        $roleAdmin = Role::create(['name' => 'admin']);
        $roleAdmin->givePermissionTo(Permission::all());
    }
}