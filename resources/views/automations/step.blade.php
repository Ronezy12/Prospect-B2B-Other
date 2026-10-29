{{-- Carte d'étape du constructeur ; $i = index ou '__INDEX__' pour le gabarit JavaScript. --}}
@php($s = $step ?? [])
@php($n = "steps[$i]")
<div class="step-card" data-step data-uid="{{ $s['id'] ?? '' }}">
    <div class="step-head">
        <span class="step-num" data-step-num>Étape</span>
        <label class="sr-only" for="type-{{ $i }}">Type d'étape</label>
        <select id="type-{{ $i }}" name="{{ $n }}[type]" data-step-type>
            @foreach (config('prospection.automation.step_types') as $k => $l)<option value="{{ $k }}" @selected(($s['type'] ?? 'action') === $k)>{{ $l }}</option>@endforeach
        </select>
        <span class="grow"></span>
        <button type="button" class="icon-btn" data-step-up aria-label="Monter">↑</button>
        <button type="button" class="icon-btn" data-step-down aria-label="Descendre">↓</button>
        <button type="button" class="icon-btn" data-step-remove aria-label="Supprimer l'étape"><x-icon name="trash-2" /></button>
    </div>

    <div class="form-grid" data-step-panel="action">
        <div class="field full">
            <label>Action</label>
            <select name="{{ $n }}[action]" data-action-select>
                @foreach (config('prospection.automation.actions') as $k => $l)<option value="{{ $k }}" @selected(($s['action'] ?? 'create_task') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="field" data-for-action="create_task notify_owner"><label>Intitulé / titre</label><input type="text" name="{{ $n }}[title]" value="{{ $s['title'] ?? '' }}" maxlength="190"></div>
        <div class="field" data-for-action="notify_owner"><label>Message</label><input type="text" name="{{ $n }}[message]" value="{{ $s['message'] ?? '' }}" maxlength="255"></div>
        <div class="field" data-for-action="create_task">
            <label>Type de tâche</label>
            <select name="{{ $n }}[task_type]">@foreach (config('prospection.task_types') as $k => $l)<option value="{{ $k }}" @selected(($s['task_type'] ?? 'relance') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field" data-for-action="create_task"><label>Échéance (jours après)</label><input type="number" name="{{ $n }}[due_days]" value="{{ $s['due_days'] ?? 1 }}" min="0" max="365"></div>
        <div class="field" data-for-action="set_company_status">
            <label>Nouveau statut</label>
            <select name="{{ $n }}[status]">@foreach (config('prospection.company_statuses') as $k => $l)<option value="{{ $k }}" @selected(($s['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field" data-for-action="add_tag"><label>Tag</label><input type="text" name="{{ $n }}[tag]" value="{{ $s['tag'] ?? '' }}" maxlength="80"></div>
        <div class="field" data-for-action="send_email">
            <label>Modèle (prospection)</label>
            <select name="{{ $n }}[template_id]"><option value="">—</option>@foreach ($templates as $t)<option value="{{ $t->id }}" @selected(($s['template_id'] ?? null) == $t->id)>{{ $t->name }}</option>@endforeach</select>
        </div>
        <div class="field" data-for-action="send_email">
            <label>Compte d'envoi</label>
            <select name="{{ $n }}[mail_account_id]"><option value="">—</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(($s['mail_account_id'] ?? null) == $a->id)>{{ $a->name }}</option>@endforeach</select>
        </div>
        <p class="small muted full" data-for-action="send_email">L'e-mail part au contact principal avec les contrôles de conformité (opposition, consentement B2C, adresse invalide) et un lien de désinscription.</p>
    </div>

    <div class="form-grid" data-step-panel="delay">
        <div class="field"><label>Jours</label><input type="number" name="{{ $n }}[days]" value="{{ $s['days'] ?? 1 }}" min="0" max="365"></div>
        <div class="field"><label>Heures</label><input type="number" name="{{ $n }}[hours]" value="{{ $s['hours'] ?? 0 }}" min="0" max="23"></div>
    </div>

    <div class="form-grid" data-step-panel="condition">
        <div class="field">
            <label>Critère</label>
            <select name="{{ $n }}[field]">@foreach (config('prospection.automation.conditions') as $k => $l)<option value="{{ $k }}" @selected(($s['field'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field">
            <label>Opérateur</label>
            <select name="{{ $n }}[operator]">@foreach (config('prospection.automation.operators') as $k => $l)<option value="{{ $k }}" @selected(($s['operator'] ?? 'is_true') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field"><label>Valeur (pour « égal / différent »)</label><input type="text" name="{{ $n }}[value]" value="{{ $s['value'] ?? '' }}" maxlength="80" placeholder="ex. non_pertinent, won, client…"></div>
        <div class="field"></div>
        <div class="field"><label>Si vrai, aller à</label><select name="{{ $n }}[then]" data-branch data-current="{{ $s['then'] ?? '' }}"></select></div>
        <div class="field"><label>Sinon, aller à</label><select name="{{ $n }}[else]" data-branch data-current="{{ $s['else'] ?? 'end' }}"></select></div>
    </div>
</div>
