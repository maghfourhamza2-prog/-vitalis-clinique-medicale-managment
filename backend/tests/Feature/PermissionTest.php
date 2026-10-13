<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * SCRUM-518 - Association des permissions aux roles.
 *
 * Verifie les deux niveaux :
 *  - la table de correspondance role -> permissions (enum Role) ;
 *  - son application reelle sur les routes de l'API.
 */
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------- table role -> permissions

    public function test_the_administrator_holds_every_permission(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                Role::ADMINISTRATEUR->accorde($permission),
                "L'administrateur devrait avoir la permission {$permission->value}"
            );
        }
    }

    public function test_only_the_administrator_may_delete_a_patient(): void
    {
        $this->assertTrue(Role::ADMINISTRATEUR->accorde(Permission::PATIENTS_DELETE));

        foreach ([Role::MEDECIN, Role::SECRETAIRE, Role::INFIRMIER, Role::PATIENT] as $role) {
            $this->assertFalse(
                $role->accorde(Permission::PATIENTS_DELETE),
                "Le role {$role->value} ne doit pas pouvoir supprimer un patient"
            );
        }
    }

    public function test_only_the_administrator_may_manage_roles(): void
    {
        $this->assertTrue(Role::ADMINISTRATEUR->accorde(Permission::ROLES_MANAGE));

        foreach ([Role::MEDECIN, Role::SECRETAIRE, Role::INFIRMIER, Role::PATIENT] as $role) {
            $this->assertFalse($role->accorde(Permission::ROLES_MANAGE));
        }
    }

    public function test_medical_staff_keeps_read_create_and_update(): void
    {
        foreach ([Role::MEDECIN, Role::SECRETAIRE, Role::INFIRMIER] as $role) {
            $this->assertTrue($role->accorde(Permission::PATIENTS_READ));
            $this->assertTrue($role->accorde(Permission::PATIENTS_CREATE));
            $this->assertTrue($role->accorde(Permission::PATIENTS_UPDATE));
        }
    }

    /**
     * SCRUM-526 - Ce test datait de SCRUM-518, ou le role patient n'avait
     * effectivement aucune permission. SCRUM-524 lui a donne la lecture de
     * ses propres donnees medicales sans mettre l'assertion a jour : elle
     * echouait depuis. On verifie desormais ce qui compte reellement, a
     * savoir que le patient reste enferme dans une lecture seule et n'a
     * aucun acces aux modules du personnel.
     */
    public function test_a_patient_only_holds_read_permissions(): void
    {
        $permissions = Role::PATIENT->permissions();

        $this->assertNotEmpty($permissions);

        // SCRUM-605 : la prise de rendez-vous par le patient lui-meme
        // (SCRUM-49) est la seule ecriture qui lui soit accordee. Tout le
        // reste doit rester en lecture : il ne modifie ni ne supprime aucun
        // dossier. Le perimetre "les siens" est applique par PerimetreDossier.
        $ecrituresAutorisees = [Permission::RENDEZ_VOUS_CREATE, Permission::AI_USE];

        foreach ($permissions as $permission) {
            if (in_array($permission, $ecrituresAutorisees, true)) {
                continue;
            }

            $this->assertStringEndsWith(
                '.read',
                $permission->value,
                "Le role patient ne doit detenir que des permissions de lecture, or il detient {$permission->value}"
            );
        }

        // Aucune suppression, quelle que soit la ressource.
        foreach ($permissions as $permission) {
            $this->assertStringEndsNotWith('.delete', $permission->value);
        }

        foreach ([
            Permission::PATIENTS_READ,
            Permission::ROLES_MANAGE,
            Permission::MEDECINS_READ,
            Permission::SPECIALITES_READ,
        ] as $interdite) {
            $this->assertFalse(
                Role::PATIENT->accorde($interdite),
                "Le role patient ne doit pas detenir {$interdite->value}"
            );
        }
    }

    public function test_financial_and_room_permissions_follow_least_privilege(): void
    {
        foreach ([
            Permission::FACTURES_READ,
            Permission::FACTURES_CREATE,
            Permission::FACTURES_UPDATE,
            Permission::PAIEMENTS_READ,
            Permission::PAIEMENTS_CREATE,
            Permission::PAIEMENTS_UPDATE,
        ] as $permission) {
            $this->assertTrue(Role::ADMINISTRATEUR->accorde($permission));
            $this->assertTrue(Role::SECRETAIRE->accorde($permission));
            $this->assertFalse(Role::MEDECIN->accorde($permission));
            $this->assertFalse(Role::INFIRMIER->accorde($permission));
            $this->assertFalse(Role::PATIENT->accorde($permission));
        }

        foreach ([Permission::FACTURES_DELETE, Permission::PAIEMENTS_DELETE] as $permission) {
            $this->assertTrue(Role::ADMINISTRATEUR->accorde($permission));
            foreach ([Role::MEDECIN, Role::SECRETAIRE, Role::INFIRMIER, Role::PATIENT] as $role) {
                $this->assertFalse($role->accorde($permission));
            }
        }

        foreach ([Role::ADMINISTRATEUR, Role::MEDECIN, Role::SECRETAIRE, Role::INFIRMIER] as $role) {
            $this->assertTrue($role->accorde(Permission::CHAMBRES_READ));
        }

        $this->assertFalse(Role::PATIENT->accorde(Permission::CHAMBRES_READ));
    }

    public function test_ai_access_is_explicit_and_not_granted_to_every_role(): void
    {
        foreach ([Role::ADMINISTRATEUR, Role::MEDECIN, Role::INFIRMIER, Role::PATIENT] as $role) {
            $this->assertTrue($role->accorde(Permission::AI_USE));
        }

        $this->assertFalse(Role::SECRETAIRE->accorde(Permission::AI_USE));
    }

    public function test_every_permission_is_registered_as_a_gate(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                Gate::has($permission->value),
                "La permission {$permission->value} devrait etre declaree comme Gate"
            );
        }
    }

    // ------------------------------------------------- application sur l API

    public function test_an_unauthenticated_visitor_gets_401_not_403(): void
    {
        // auth:sanctum intervient avant la permission : l'absence de jeton doit
        // rester un 401, et non un 403.
        $this->getJson('/api/patients')->assertStatus(401);
        $this->deleteJson('/api/patients/1')->assertStatus(401);
        $this->getJson('/api/roles')->assertStatus(401);
    }

    public function test_staff_roles_can_read_patients(): void
    {
        foreach (['administrateur', 'medecin', 'secretaire', 'infirmier'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/patients')
                ->assertStatus(200);
        }
    }

    public function test_a_patient_cannot_read_patients(): void
    {
        $user = User::factory()->create(['role' => 'patient']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/patients')
            ->assertStatus(403);
    }

    public function test_deleting_a_patient_is_refused_to_non_administrators(): void
    {
        foreach (['medecin', 'secretaire', 'infirmier', 'patient'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->deleteJson('/api/patients/1')
                ->assertStatus(403);
        }
    }

    public function test_deleting_a_patient_passes_the_permission_check_for_an_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'administrateur']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson('/api/patients/999999');

        // Le patient 999999 n'existe pas : l'important est que la reponse ne
        // soit pas un 403, donc que la permission ait bien ete accordee.
        $this->assertNotSame(403, $response->status());
    }

    public function test_role_management_is_refused_to_non_administrators(): void
    {
        foreach (['medecin', 'secretaire', 'infirmier', 'patient'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/roles')
                ->assertStatus(403);
        }
    }

    public function test_role_management_is_allowed_for_the_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'administrateur']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/roles')
            ->assertStatus(200)
            ->assertJsonStructure(['roles']);
    }

    public function test_the_refusal_message_is_unchanged(): void
    {
        // Le passage du middleware 'role' au middleware natif 'can' ne doit pas
        // modifier la reponse vue par le frontend.
        $user = User::factory()->create(['role' => 'patient']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/patients')
            ->assertStatus(403)
            ->assertJson(['message' => 'Accès interdit']);
    }

    public function test_an_unknown_role_grants_nothing(): void
    {
        // Valeur inattendue en base (import, saisie manuelle) : on refuse par
        // defaut plutot que d'accorder l'acces.
        $user = User::factory()->create(['role' => 'role-inconnu']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/patients')
            ->assertStatus(403);
    }
}
