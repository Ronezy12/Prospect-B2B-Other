{{-- Résultat d'un appel en un clic : l'outil met à jour la fiche, l'affaire et crée le prochain rappel. --}}
@can('update', $company)
    <form method="POST" action="{{ route('pm.companies.outcome', $company) }}" class="s-outcomes" aria-label="Résultat de l'appel">
        @csrf
        <button type="submit" name="outcome" value="pas_de_reponse" class="s-out"><x-icon name="phone-off" /><span>Pas de réponse</span></button>
        <button type="submit" name="outcome" value="interesse" class="s-out good"><x-icon name="thumbs-up" /><span>Intéressé</span></button>
        <button type="submit" name="outcome" value="rdv" class="s-out good"><x-icon name="calendar-check" /><span>Rendez-vous</span></button>
        <button type="submit" name="outcome" value="gagne" class="s-out win"><x-icon name="trophy" /><span>Signé</span></button>
        <button type="submit" name="outcome" value="pas_interesse" class="s-out bad"><x-icon name="thumbs-down" /><span>Pas intéressé</span></button>
    </form>
@endcan
