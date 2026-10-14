<?php

namespace App\Http\Controllers;

use App\Auth\PerimetreDossier;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * SCRUM-568 - Recupere un dossier sans reveler son existence a qui n'y a
     * pas droit.
     *
     * findOrFail() repond 404 pour un identifiant inexistant, tandis que le
     * controle d'acces repond 403 pour un dossier appartenant a autrui. La
     * difference entre les deux reponses suffit a enumerer les identifiants
     * reellement utilises dans la clinique en faisant varier l'URL.
     *
     * Un role au perimetre restreint (medecin, patient) recoit donc le meme
     * refus dans les deux cas. Un role au perimetre global garde un 404 :
     * "introuvable" est pour lui une information legitime, et la masquer
     * nuirait au diagnostic.
     *
     * SCRUM-605 : place ici plutot que recopie une troisieme fois. Trois
     * copies d'un controle d'acces finissent par diverger, et c'est la copie
     * oubliee qui devient la faille.
     */
    protected function trouverOuRefuser(?object $dossier, Request $request): object
    {
        if ($dossier !== null) {
            return $dossier;
        }

        $restreint = PerimetreDossier::perimetreRestreint($request->user());

        abort(
            $restreint ? 403 : 404,
            $restreint ? 'Accès interdit' : 'Ressource introuvable'
        );
    }
}
