<?php

namespace App\Http\Controllers;

use App\Models\GuruTendik;

class GuruTendikProfileController extends Controller
{
    public function __invoke(GuruTendik $guruTendik)
    {
        $guruTendik->load([
            'strukturOrganisasiNode',
            'tugasTambahanAktif',
        ]);

        abort_unless($guruTendik->strukturOrganisasiNode, 404);

        return view('guru-tendik.profile', [
            'title' => $guruTendik->nama,
            'guruTendik' => $guruTendik,
            'strukturNode' => $guruTendik->strukturOrganisasiNode,
        ]);
    }
}
