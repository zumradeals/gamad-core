# Inventaire de reprise des écrans GAMAD Réseau

Source figée au commit `83d1a1df1496f322a5e718996c9420120d021ec0` de `zumradeals/gamad`. Comparaison avec `gamad-core` sur la PR #104.

Le registre ci-dessous couvre les déclarations HTTP de `routes/web.php`, les écrans Fortify déclarés par le dépôt et tous les gabarits Blade versionnés. Les gabarits Livewire recensent aussi les écrans, modales et états dont les actions ne sont pas des routes distinctes. Les règles de chaque parcours restent à vérifier dans le code source et les tests avant clôture.

**Statuts**. Disponible signifie que le parcours indiqué est utilisable dans Core. Partiel signifie qu’un équivalent existe et que la parité reste à établir. À reprendre signifie qu’aucun équivalent direct n’a été repéré dans les routes ou écrans recensés.

## Routes déclarées par le dépôt source

125 déclarations HTTP recensées. Les lignes renvoient au fichier source figé.

| Méthode et chemin source | Nom source | Équivalent Core | État ou écart |
| --- | --- | --- | --- |
| [`GET /`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L99) | `home` | `VIEW /` | Partiel · Sections dynamiques, recherche et navigation source à comparer |
| [`GET /aide`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L102) | `aide` | `VIEW /aide` | Partiel · Contenu source partiellement repris |
| [`GET /contact`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L103) | `contact` | `GET /contact` | Disponible · Formulaire public et traitement Core disponibles |
| [`POST /contact`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L104) | `contact.store` | `POST /contact` | Disponible · Envoi public et protections testés |
| [`GET /lieux/recherche`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L105) | `lieux.search` | — | À reprendre |
| [`GET /charte`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L106) | `charte` | — | À reprendre |
| [`GET /mentions-legales`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L107) | `mentions` | — | À reprendre |
| [`VIEW /decouvrir`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L111) | `decouvrir` | — | À reprendre |
| [`GET /besoins`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L112) | `besoins.index` | — | À reprendre |
| [`GET /personnes`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L113) | `personnes.index` | `GET /personnes` | Partiel · Recherche et visibilité disponibles, filtres territoriaux à reprendre |
| [`GET /personnes/{profile:public_id}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L114) | `personnes.show` | `GET /personnes/{reference}` | Partiel · Fiche disponible, photo et blocs source à reprendre |
| [`GET /personnes/{profile:public_id}/photo`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L115) | `personnes.photo` | — | À reprendre |
| [`GET /opportunites`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L118) | `opportunites.index` | `GET /opportunites` | Partiel · Catalogue disponible, filtres et rapprochements à reprendre |
| [`GET /opportunites/{opportunity}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L119) | `opportunites.show` | `GET /opportunites/{reference}` | Partiel · Offre et candidature textuelle disponibles, modes et pièces jointes à reprendre |
| [`GET /entreprises`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L120) | `entreprises.index` | `GET /entreprises` | Partiel · Annuaire disponible, filtres et ergonomie source à comparer |
| [`GET /entreprises/{company}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L121) | `entreprises.show` | `GET /entreprises/{reference}` | Partiel · Fiche et opportunités disponibles, identité visuelle, services et expériences à reprendre |
| [`GET /entreprises/{company}/visuel/{kind}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L122) | `entreprises.visual` | — | À reprendre |
| [`GET /zumra`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L126) | `zumras.index` | `GET /zumra` | Partiel · Catalogue disponible, filtres et parcours spécialisés à reprendre |
| [`GET /rapport-contributions`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L127) | `rapport-contributions` | — | À reprendre |
| [`GET /zumra/{zumra}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L128) | `zumras.show` | `GET /zumra/{reference}` | Partiel · Fiche, adhésions et accueil disponibles, autres actions de la ZUMRA à reprendre |
| [`GET /projets`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L131) | `projets.index` | `GET /projets` | Partiel · Catalogue et dossiers disponibles, filtres et navigation source à reprendre |
| [`GET /projets/{project}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L134) | `projets.show` | `GET /projets/{reference}` | Partiel · Dossier, étapes, équipe et vitrine disponibles, autres actions à reprendre |
| [`GET /publications/{publication}/fichier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L144) | `publications.media` | — | À reprendre |
| [`GET /publications/{publication}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L145) | `publications.show` | `GET /publications/{reference}` | Partiel · Lecture et commentaires simples, actions et réponses avancées à reprendre |
| [`GET /compte-suspendu`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L147) | `suspendu` | — | À reprendre |
| [`GET /entreprises/creer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L150) | `entreprises.create` | `GET /entreprises/creer` | Partiel · Soumission RCCM, DFE et IDU disponible, champs source à compléter |
| [`GET /entreprises/{company}/contacter`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L151) | `entreprises.contact` | — | À reprendre |
| [`POST /entreprises/{company}/contacter`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L153) | `entreprises.contact.store` | — | À reprendre |
| [`GET /entreprises/{company}/corriger`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L155) | `entreprises.resubmit` | `GET /entreprises/{reference}` | Partiel · Formulaire intégré à la fiche du propriétaire après refus |
| [`POST /entreprises/{company}/corriger`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L157) | `entreprises.resubmit.store` | `POST /entreprises/{reference}/resoumettre` | Partiel · Nouvelle soumission et contrôle de révision disponibles |
| [`GET /entreprises/{company}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L159) | `entreprises.reports.create` | — | À reprendre |
| [`POST /entreprises/{company}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L161) | `entreprises.reports.store` | — | À reprendre |
| [`GET /entreprises/{company}/identite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L163) | `entreprises.branding.edit` | — | À reprendre |
| [`POST /entreprises/{company}/identite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L165) | `entreprises.branding.update` | — | À reprendre |
| [`GET /entreprises/{company}/publier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L167) | `entreprises.posts.create` | — | À reprendre |
| [`POST /entreprises/{company}/publier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L169) | `entreprises.posts.store` | — | À reprendre |
| [`GET /entreprises/{company}/opportunites`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L172) | `entreprises.opportunities` | `GET /entreprises/{reference}` | Partiel · Liste intégrée à la fiche, gestion dédiée à reprendre |
| [`GET /entreprises/{company}/opportunites/nouvelle`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L173) | `opportunites.create` | `GET /entreprises/{reference}` | Partiel · Publication depuis la fiche, création avancée à reprendre |
| [`GET /opportunites/{opportunity}/modifier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L174) | `opportunites.edit` | — | À reprendre |
| [`GET /opportunites/{opportunity}/reponses`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L175) | `opportunites.responses` | `GET /opportunites/{reference}` | Partiel · Réponses visibles du propriétaire, écran dédié à reprendre |
| [`GET /opportunites/{opportunity}/personnes`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L176) | `opportunites.people` | — | À reprendre |
| [`POST /opportunites/{opportunity}/sauvegarder`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L177) | `opportunites.save` | — | À reprendre |
| [`GET /opportunites/{opportunity}/repondre`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L178) | `opportunites.respond` | `GET /opportunites/{reference}` | Partiel · Formulaire textuel intégré à la fiche |
| [`POST /opportunites/{opportunity}/repondre`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L179) | `opportunites.respond.store` | `POST /opportunites/{reference}/candidatures` | Partiel · Candidature textuelle disponible |
| [`POST /opportunites/reponses/{response}/retirer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L180) | `opportunites.withdraw` | — | À reprendre |
| [`GET /opportunites/reponses/{response}/document`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L181) | `opportunites.attachment` | — | À reprendre |
| [`GET /espace/opportunites`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L182) | `espace.opportunites` | — | À reprendre |
| [`GET /espace/experiences-professionnelles`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L183) | `espace.company-experiences` | — | À reprendre |
| [`POST /espace/experiences-professionnelles`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L184) | `espace.company-experiences.store` | — | À reprendre |
| [`POST /espace/experiences-professionnelles/{experience}/retirer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L185) | `espace.company-experiences.withdraw` | — | À reprendre |
| [`POST /espace/experiences-professionnelles/{experience}/visibilite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L187) | `espace.company-experiences.visibility` | — | À reprendre |
| [`GET /entreprises/{company}/confirmations`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L189) | `entreprises.experiences.review` | — | À reprendre |
| [`POST /entreprises/{company}/confirmations/{experience}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L191) | `entreprises.experiences.decision` | — | À reprendre |
| [`POST /entreprises/{company}/suivre`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L193) | `entreprises.follow` | — | À reprendre |
| [`GET /moderation/entreprises`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L194) | `moderation.entreprises` | `GET /console/entreprises` | Partiel · Vérification et suspension disponibles, flux source à comparer |
| [`GET /moderation/entreprises/{company}/documents/{document}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L195) | `entreprises.document` | `GET /console/entreprises/{reference}/justificatif/{type?}` | Partiel · Téléchargement privé RCCM, DFE et IDU |
| [`GET /espace/attestations`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L197) | `espace.attestations` | — | À reprendre |
| [`GET /espace/enregistrements`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L198) | `espace.enregistrements` | — | À reprendre |
| [`POST /publications/{publication}/enregistrer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L199) | `publications.bookmark` | — | À reprendre |
| [`GET /moderation/confiance`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L200) | `moderation.confiance` | — | À reprendre |
| [`GET /personnes/{profile:public_id}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L201) | `personnes.signaler` | — | À reprendre |
| [`GET /espace/zumras`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L202) | `espace.zumras` | — | À reprendre |
| [`GET /zumra/creer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L203) | `zumras.create` | `GET /zumra/nouvelle` | Partiel · Parcours équivalent repéré, parité à vérifier |
| [`GET /zumra/{zumra}/inviter`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L204) | `zumras.invite` | — | À reprendre |
| [`GET /zumra/{zumra}/publier/{slug}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L205) | `zumras.publier` | — | À reprendre |
| [`GET /zumra/{zumra}/modifier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L206) | `zumras.edit` | — | À reprendre |
| [`GET /zumra/{zumra}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L207) | `zumras.report` | — | À reprendre |
| [`GET /zumra/{zumra}/discussion/{post}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L208) | `zumras.discussion.report` | — | À reprendre |
| [`GET /espace/projets`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L209) | `espace.projets` | `GET /projets` | Partiel · Dossiers du membre accessibles, écran personnel à reprendre |
| [`GET /contributions`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L210) | `contributions.index` | — | À reprendre |
| [`GET /contributions/besoin/nouveau`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L211) | `contributions.need` | — | À reprendre |
| [`GET /contributions/proposer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L212) | `contributions.propose` | — | À reprendre |
| [`GET /contributions/{proposal}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L213) | `contributions.show` | — | À reprendre |
| [`GET /espace/mes-contributions`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L214) | `espace.mes-contributions` | — | À reprendre |
| [`GET /espace/services`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L215) | `espace.services` | — | À reprendre |
| [`GET /espace/publicite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L216) | `publicite.campagnes` | — | À reprendre |
| [`GET /espace/publicite/nouvelle`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L217) | `publicite.creer` | — | À reprendre |
| [`GET /espace/publicite/{campaign}/modifier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L218) | `publicite.modifier` | — | À reprendre |
| [`GET /publicite/{campaign}/aller`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L219) | `publicite.cta` | — | À reprendre |
| [`GET /espace/publicite/resultats`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L220) | `publicite.resultats` | — | À reprendre |
| [`GET /publicite/{campaign}/image`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L221) | `publicite.image` | — | À reprendre |
| [`GET /projets/creer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L222) | `projets.new` | `GET /projets/nouveau` | Partiel · Formulaire Core disponible |
| [`GET /zumra/{zumra}/projets/creer`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L223) | `projets.create` | `GET /projets/nouveau` | Partiel · Parcours équivalent repéré, parité à vérifier |
| [`GET /projets/{project}/modifier`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L224) | `projets.edit` | — | À reprendre |
| [`GET /projets/{project}/publier/{slug}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L225) | `projets.publier` | — | À reprendre |
| [`GET /fil`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L226) | `fil` | `GET /fil` | Partiel · Fil textuel disponible, filtres, pièces jointes et types restants |
| [`VIEW /agir`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L227) | `agir` | — | À reprendre |
| [`GET /agir/{slug}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L228) | `agir.publier` | — | À reprendre |
| [`GET /publications/{publication}/signaler`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L229) | `publications.signaler` | `POST /publications/{reference}/signalements/{commentaire?}` | Partiel · Action intégrée à la fiche, écran dédié à reprendre |
| [`GET /moderation`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L230) | `moderation` | `GET /console/moderation-reseau` | Partiel · Signalements et décisions de contenu, règles avancées à reprendre |
| [`GET /moderation/personnes`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L231) | `moderation.personnes` | — | À reprendre |
| [`GET /moderation/commentaires`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L232) | `moderation.commentaires` | `GET /console/moderation-reseau` | Partiel · File partagée, écran dédié et restauration à reprendre |
| [`GET /moderation/zumras`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L233) | `moderation.zumras` | — | À reprendre |
| [`GET /moderation/annonces`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L234) | `moderation.annonces` | — | À reprendre |
| [`GET /moderation/journal`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L235) | `moderation.journal` | — | À reprendre |
| [`GET /espace/portefeuille`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L236) | `portefeuille` | — | À reprendre |
| [`GET /zumra/{zumra}/portefeuille`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L237) | `zumras.portefeuille` | — | À reprendre |
| [`GET /espace/contribution`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L238) | `contribution` | — | À reprendre |
| [`GET /zumra/{zumra}/contribution`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L239) | `zumras.contribution` | — | À reprendre |
| [`GET /espace/avis`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L240) | `avis` | — | À reprendre |
| [`GET /conversations/{conversation}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L241) | `conversations.show` | `GET /conversations/{reference}` | Partiel · Conversation textuelle de relation disponible, autres origines et actions à reprendre |
| [`GET /notifications`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L242) | `notifications` | `GET /notifications` | Partiel · Notifications de relation et de message disponibles, autres événements à reprendre |
| [`GET /notifications/{notification}`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L243) | `notifications.open` | `POST /notifications/{reference}/lire` | Partiel · Marquage et lien disponibles, ouverture source à comparer |
| [`GET /espace`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L244) | `espace` | — | À reprendre |
| [`GET /espace/profil`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L245) | `espace.profil` | `GET /mon-profil` | Partiel · Photo, territoires et domaines à reprendre |
| [`GET /espace/parametres/identite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L246) | `espace.parametres.identite` | — | À reprendre |
| [`POST /espace/parametres/identite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L247) | `espace.parametres.identite.save` | — | À reprendre |
| [`GET /espace/traitements`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L248) | `espace.traitements` | — | À reprendre |
| [`GET /espace/parametres`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L249) | `espace.parametres` | `GET /console/reseau` | Partiel · Tableau de bord Core disponible, sous-écrans source à reprendre |
| [`GET /espace/parametres/textes`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L250) | `espace.parametres.textes` | — | À reprendre |
| [`GET /espace/parametres/zumra`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L251) | `espace.parametres.zumra` | — | À reprendre |
| [`GET /espace/parametres/projets`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L252) | `espace.parametres.projets` | — | À reprendre |
| [`GET /espace/parametres/publicite`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L253) | `espace.parametres.publicite` | — | À reprendre |
| [`GET /espace/parametres/contributions`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L254) | `espace.parametres.contributions` | — | À reprendre |
| [`GET /espace/parametres/paiement`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L255) | `espace.parametres.paiement` | — | À reprendre |
| [`GET /espace/messages`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L256) | `espace.messages` | `GET /console/contact` | Partiel · Liste et traitement disponibles, ergonomie source à comparer |
| [`GET /espace/contributions`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L257) | `espace.contributions` | — | À reprendre |
| [`GET /espace/reversements`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L258) | `espace.reversements` | — | À reprendre |
| [`GET /espace/paiements`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L259) | `espace.paiements` | — | À reprendre |
| [`GET /espace/parametres/portefeuille`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L260) | `espace.parametres.portefeuille` | — | À reprendre |
| [`GET /espace/parametres/copie`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L261) | `espace.parametres.copie` | — | À reprendre |
| [`GET /espace/parametres/domaines`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L262) | `espace.parametres.domaines` | — | À reprendre |
| [`GET /espace/parametres/territoires`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L263) | `espace.parametres.territoires` | — | À reprendre |
| [`POST /webhooks/geniuspay`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L267) | `webhooks.geniuspay` | — | À reprendre |
| [`GET /identite/{kind}.png`](https://github.com/zumradeals/gamad/blob/83d1a1df1496f322a5e718996c9420120d021ec0/routes/web.php#L269) | `branding.image` | — | À reprendre |

## Écrans d’authentification déclarés par Fortify

| Écran source | Équivalent Core | État |
| --- | --- | --- |
| Connexion | `/connexion` | Disponible · compte Core, identifiant vérifié |
| Inscription | `/inscription` | Disponible · parcours e-mail Core |
| Vérification de l’e-mail | `/inscription/verification` | Disponible · vérification et renvoi |
| Mot de passe oublié | — | À reprendre |
| Réinitialisation du mot de passe | — | À reprendre |
| Confirmation du mot de passe | — | Désactivée dans le dépôt source, réponse 404 |

## Gabarits Blade versionnés

175 fichiers. Ce registre de fichiers identifie les éléments à comparer avec les routes, y compris les composants réutilisés et les pages d’erreur.

### aide (2)

- `aide/contenu.blade.php`
- `aide/show.blade.php`

### auth (5)

- `auth/forgot-password.blade.php`
- `auth/login.blade.php`
- `auth/register.blade.php`
- `auth/reset-password.blade.php`
- `auth/verify-email.blade.php`

### components (32)

- `components/agir-actions.blade.php`
- `components/auth-field.blade.php`
- `components/avatar.blade.php`
- `components/brand-icons.blade.php`
- `components/button.blade.php`
- `components/city-picker.blade.php`
- `components/company-discovery-card.blade.php`
- `components/company-logo.blade.php`
- `components/contribution-entry.blade.php`
- `components/field.blade.php`
- `components/header-search.blade.php`
- `components/icon.blade.php`
- `components/layouts/app.blade.php`
- `components/layouts/guest.blade.php`
- `components/layouts/landing.blade.php`
- `components/layouts/public.blade.php`
- `components/logo.blade.php`
- `components/need-discovery-card.blade.php`
- `components/needs-illustration.blade.php`
- `components/opportunity-card.blade.php`
- `components/opportunity-conditions.blade.php`
- `components/people-illustration.blade.php`
- `components/person-discovery-card.blade.php`
- `components/profile-next-step.blade.php`
- `components/profile-share.blade.php`
- `components/projects-illustration.blade.php`
- `components/publication-card.blade.php`
- `components/publication-media.blade.php`
- `components/status.blade.php`
- `components/wire-field.blade.php`
- `components/work-reference.blade.php`
- `components/zumra-initials.blade.php`

### contact (2)

- `contact/form.blade.php`
- `contact/show.blade.php`

### errors (7)

- `errors/403.blade.php`
- `errors/404.blade.php`
- `errors/419.blade.php`
- `errors/429.blade.php`
- `errors/500.blade.php`
- `errors/503.blade.php`
- `errors/page.blade.php`

### livewire (100)

- `livewire/attester-realisation.blade.php`
- `livewire/avis-moderation.blade.php`
- `livewire/carrefour-besoins.blade.php`
- `livewire/carrefour-contributions.blade.php`
- `livewire/carrefour-personnes.blade.php`
- `livewire/carrefour-projets.blade.php`
- `livewire/carrefour-zumras.blade.php`
- `livewire/company-create.blade.php`
- `livewire/company-directory.blade.php`
- `livewire/company-moderation.blade.php`
- `livewire/consentement-publicite.blade.php`
- `livewire/contributions-administration.blade.php`
- `livewire/core-console/actions.blade.php`
- `livewire/core-console/health.blade.php`
- `livewire/core-console/identity.blade.php`
- `livewire/creer-campagne.blade.php`
- `livewire/espace.blade.php`
- `livewire/fil-composer.blade.php`
- `livewire/fil.blade.php`
- `livewire/file-moderation.blade.php`
- `livewire/formulaire-projet.blade.php`
- `livewire/formulaire-zumra.blade.php`
- `livewire/gesture-sheet.blade.php`
- `livewire/inviter-personnes.blade.php`
- `livewire/journal-administration.blade.php`
- `livewire/match-suggestions.blade.php`
- `livewire/mes-attestations.blade.php`
- `livewire/mes-campagnes.blade.php`
- `livewire/mes-contributions.blade.php`
- `livewire/mes-enregistrements.blade.php`
- `livewire/mes-projets.blade.php`
- `livewire/mes-zumras.blade.php`
- `livewire/messages-contact.blade.php`
- `livewire/moderation-annonces.blade.php`
- `livewire/moderation-commentaires.blade.php`
- `livewire/moderation-confiance.blade.php`
- `livewire/moderation-personnes.blade.php`
- `livewire/moderation-zumras.blade.php`
- `livewire/modifier-profil.blade.php`
- `livewire/mon-acces.blade.php`
- `livewire/monde-projet.blade.php`
- `livewire/monde-zumra.blade.php`
- `livewire/my-network.blade.php`
- `livewire/notification-bell.blade.php`
- `livewire/opportunity-catalog.blade.php`
- `livewire/opportunity-form.blade.php`
- `livewire/opportunity-manager.blade.php`
- `livewire/opportunity-people.blade.php`
- `livewire/opportunity-responses.blade.php`
- `livewire/page-contribution.blade.php`
- `livewire/page-conversation.blade.php`
- `livewire/page-notifications.blade.php`
- `livewire/page-portefeuille.blade.php`
- `livewire/page-proposition.blade.php`
- `livewire/page-publication.blade.php`
- `livewire/page-services-gamad.blade.php`
- `livewire/paiements-administration.blade.php`
- `livewire/parametres-contributions.blade.php`
- `livewire/parametres-copie.blade.php`
- `livewire/parametres-domaines.blade.php`
- `livewire/parametres-paiement.blade.php`
- `livewire/parametres-portefeuille.blade.php`
- `livewire/parametres-projets.blade.php`
- `livewire/parametres-publicite.blade.php`
- `livewire/parametres-territoires.blade.php`
- `livewire/parametres-textes.blade.php`
- `livewire/parametres-zumra.blade.php`
- `livewire/parametres.blade.php`
- `livewire/partials/besoin-contributions.blade.php`
- `livewire/partials/contribution-need-card.blade.php`
- `livewire/partials/contribution-proposal-card.blade.php`
- `livewire/partials/gratitude-form.blade.php`
- `livewire/partials/matching-profile-editor.blade.php`
- `livewire/partials/matching-publication-editor.blade.php`
- `livewire/partials/matching-schedule.blade.php`
- `livewire/partials/moderation-actions.blade.php`
- `livewire/partials/moderation-nav.blade.php`
- `livewire/partials/profile-details-editor.blade.php`
- `livewire/partials/projet-gestion.blade.php`
- `livewire/partials/publication-comment.blade.php`
- `livewire/partials/publicite-apercu.blade.php`
- `livewire/partials/publicite-entete.blade.php`
- `livewire/partials/publier-legacy.blade.php`
- `livewire/partials/skill-picker.blade.php`
- `livewire/partials/zumra-card.blade.php`
- `livewire/person-contact.blade.php`
- `livewire/profile-gratitudes.blade.php`
- `livewire/projet-vitrine.blade.php`
- `livewire/proposer-contribution.blade.php`
- `livewire/publication-discussion.blade.php`
- `livewire/publier-besoin.blade.php`
- `livewire/publier-publication.blade.php`
- `livewire/rapport-contributions.blade.php`
- `livewire/resultats-campagnes.blade.php`
- `livewire/reversements-administration.blade.php`
- `livewire/search-status.blade.php`
- `livewire/signaler-profil.blade.php`
- `livewire/signaler-publication.blade.php`
- `livewire/signaler-zumra.blade.php`
- `livewire/traitements-core.blade.php`

### pages (24)

- `pages/agir.blade.php`
- `pages/branding.blade.php`
- `pages/charte.blade.php`
- `pages/company-branding.blade.php`
- `pages/company-contact.blade.php`
- `pages/company-experience-review.blade.php`
- `pages/company-experiences.blade.php`
- `pages/company-post-create.blade.php`
- `pages/company-report.blade.php`
- `pages/company-resubmit.blade.php`
- `pages/decouvrir.blade.php`
- `pages/entreprise.blade.php`
- `pages/mentions-legales.blade.php`
- `pages/opportunity-mine.blade.php`
- `pages/opportunity-respond.blade.php`
- `pages/opportunity-show.blade.php`
- `pages/partials-person-interests.blade.php`
- `pages/partials-person-publications.blade.php`
- `pages/partials-person-showcase.blade.php`
- `pages/partials-person-side.blade.php`
- `pages/personne.blade.php`
- `pages/projet.blade.php`
- `pages/suspendu.blade.php`
- `pages/welcome.blade.php`

### partials (3)

- `partials/discover-cards.blade.php`
- `partials/legal-links.blade.php`
- `partials/publication-social-actions.blade.php`

## Administration Réseau dans le Core

`/console/reseau` rassemble les files déjà disponibles et affiche leurs compteurs. Son accès suit le même contrôle Core que les écrans de traitement. Les écrans d’administration, réglages et modérations spécialisés demeurent dans le registre des routes ci-dessus.
