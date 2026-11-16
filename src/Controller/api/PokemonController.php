<?php

declare(strict_types=1);

namespace App\Controller\api;

use App\Repository\PokemonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pokemon', name: 'api_pokemon')]
class PokemonController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(PokemonRepository $repo): JsonResponse
    {
        $pokemons = $repo->findAll();
        $tab = [];

        foreach ($pokemons as $pokemon) {
            $types = [];
            foreach ($pokemon->getTypes() as $type) {
                $types[] = $type->getLabel();
            }

            $tab[] = [
                'id' => $pokemon->getId(),
                'name' => $pokemon->getName(),
                'hp' => $pokemon->getHp(),
                'cp' => $pokemon->getCp(),
                'picture' => $pokemon->getPicture(),
                'level' => $pokemon->getLevel(),
                'types' => $types,
            ];
        }

        return $this->json($tab);
    }
}
