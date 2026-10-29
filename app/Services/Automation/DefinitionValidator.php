<?php

namespace App\Services\Automation;

/** Vérifie la cohérence d'un scénario avant enregistrement. */
class DefinitionValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, string> erreurs
     */
    public function validate(array $steps): array
    {
        $errors = [];
        if (! $steps) {
            return ['Ajoutez au moins une étape.'];
        }
        if (count($steps) > 30) {
            $errors[] = 'Un scénario est limité à 30 étapes.';
        }

        $ids = array_column($steps, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'Chaque étape doit avoir un identifiant unique.';
        }
        $targets = array_merge($ids, ['end']);

        foreach ($steps as $i => $step) {
            $n = $i + 1;
            $type = $step['type'] ?? null;
            if (! array_key_exists((string) $type, config('prospection.automation.step_types'))) {
                $errors[] = "Étape {$n} : type inconnu.";

                continue;
            }
            if ($type === 'delay' && ((int) ($step['days'] ?? 0) * 1440 + (int) ($step['hours'] ?? 0) * 60) <= 0) {
                $errors[] = "Étape {$n} : indiquez une durée d'attente.";
            }
            if ($type === 'condition') {
                if (! array_key_exists((string) ($step['field'] ?? ''), config('prospection.automation.conditions'))) {
                    $errors[] = "Étape {$n} : critère de condition inconnu.";
                }
                if (! array_key_exists((string) ($step['operator'] ?? ''), config('prospection.automation.operators'))) {
                    $errors[] = "Étape {$n} : opérateur inconnu.";
                }
                foreach (['then' => 'si vrai', 'else' => 'sinon'] as $branch => $label) {
                    if (! in_array($step[$branch] ?? '', $targets, true)) {
                        $errors[] = "Étape {$n} : la branche « {$label} » doit mener à une étape existante ou à la fin.";
                    }
                }
            }
            if ($type === 'action') {
                $action = $step['action'] ?? null;
                if (! array_key_exists((string) $action, config('prospection.automation.actions'))) {
                    $errors[] = "Étape {$n} : action inconnue.";
                } elseif ($action === 'create_task' && blank($step['title'] ?? null)) {
                    $errors[] = "Étape {$n} : indiquez l'intitulé de la tâche.";
                } elseif ($action === 'notify_owner' && blank($step['title'] ?? null)) {
                    $errors[] = "Étape {$n} : indiquez le titre de la notification.";
                } elseif ($action === 'set_company_status' && ! array_key_exists((string) ($step['status'] ?? ''), config('prospection.company_statuses'))) {
                    $errors[] = "Étape {$n} : choisissez un statut.";
                } elseif ($action === 'add_tag' && blank($step['tag'] ?? null)) {
                    $errors[] = "Étape {$n} : indiquez le tag.";
                } elseif ($action === 'send_email' && (empty($step['template_id']) || empty($step['mail_account_id']))) {
                    $errors[] = "Étape {$n} : choisissez le modèle et le compte d'envoi.";
                }
            }
        }

        return $errors;
    }
}
