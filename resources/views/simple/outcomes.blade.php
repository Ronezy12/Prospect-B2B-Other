{{-- Mode simple : 5 gros boutons « Comment ça s'est passé ? ». L'outil fait le reste. --}}
@can('update', $company)
    <form method="POST" action="{{ route('pm.companies.outcome', $company) }}" class="s-outcomes">
        @csrf
        <button type="submit" name="outcome" value="pas_de_reponse" class="s-out">📵<span>Pas de réponse</span></button>
        <button type="submit" name="outcome" value="interesse" class="s-out good">👍<span>Intéressé</span></button>
        <button type="submit" name="outcome" value="rdv" class="s-out good">📅<span>Rendez-vous</span></button>
        <button type="submit" name="outcome" value="gagne" class="s-out win">🎉<span>Signé !</span></button>
        <button type="submit" name="outcome" value="pas_interesse" class="s-out bad">👎<span>Pas intéressé</span></button>
    </form>
@endcan
