<?php

namespace App\Services\Automation;

/** Scénarios prêts à l'emploi (exemples du cahier des charges), modifiables avant activation. */
final class Presets
{
    public static function all(): array
    {
        return [
            'nouveau_prospect' => [
                'name' => 'Nouveau prospect : créer une tâche de qualification',
                'trigger' => 'company.created',
                'steps' => [
                    ['id' => 's1', 'type' => 'action', 'action' => 'create_task', 'title' => 'Qualifier le nouveau prospect', 'task_type' => 'appel', 'due_days' => 1],
                ],
            ],
            'changement_statut' => [
                'name' => 'Changement de statut : notifier le responsable',
                'trigger' => 'company.status_changed',
                'steps' => [
                    ['id' => 's1', 'type' => 'action', 'action' => 'notify_owner', 'title' => 'Statut modifié', 'message' => 'Le statut commercial de ce prospect a changé.'],
                ],
            ],
            'relance_pertinente' => [
                'name' => 'Délai écoulé : relancer si c\'est encore pertinent',
                'trigger' => 'company.created',
                'steps' => [
                    ['id' => 's1', 'type' => 'delay', 'days' => 3],
                    ['id' => 's2', 'type' => 'condition', 'field' => 'company.replied_since_start', 'operator' => 'is_false', 'then' => 's3', 'else' => 'end'],
                    ['id' => 's3', 'type' => 'condition', 'field' => 'company.status', 'operator' => 'not_equals', 'value' => 'non_pertinent', 'then' => 's4', 'else' => 'end'],
                    ['id' => 's4', 'type' => 'condition', 'field' => 'company.opposed', 'operator' => 'is_false', 'then' => 's5', 'else' => 'end'],
                    ['id' => 's5', 'type' => 'action', 'action' => 'create_task', 'title' => 'Relancer le prospect', 'task_type' => 'relance', 'due_days' => 0],
                ],
            ],
            'reponse_recue' => [
                'name' => 'Réponse reçue : suspendre les relances automatiques',
                'trigger' => 'email.replied',
                'steps' => [
                    ['id' => 's1', 'type' => 'action', 'action' => 'stop_followups'],
                    ['id' => 's2', 'type' => 'action', 'action' => 'notify_owner', 'title' => 'Réponse reçue', 'message' => 'Un contact a répondu : les relances automatiques sont suspendues.'],
                ],
            ],
            'desabonnement' => [
                'name' => 'Désinscription : arrêter toute sollicitation',
                'trigger' => 'email.unsubscribed',
                'steps' => [
                    ['id' => 's1', 'type' => 'action', 'action' => 'stop_followups'],
                    ['id' => 's2', 'type' => 'action', 'action' => 'notify_owner', 'title' => 'Désinscription', 'message' => 'Ce contact s\'est désinscrit : il est exclu des campagnes concernées. Ne plus le solliciter par e-mail.'],
                ],
            ],
            'opportunite_gagnee' => [
                'name' => 'Opportunité gagnée : enregistrer la conversion',
                'trigger' => 'opportunity.won',
                'steps' => [
                    ['id' => 's1', 'type' => 'action', 'action' => 'convert_to_client'],
                    ['id' => 's2', 'type' => 'action', 'action' => 'notify_owner', 'title' => 'Nouveau client', 'message' => 'Opportunité gagnée : le prospect est désormais client.'],
                ],
            ],
        ];
    }
}
