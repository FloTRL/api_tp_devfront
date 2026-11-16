<?php

declare(strict_types=1);

namespace App\Controller\api;

use App\Entity\Pokemon;
use App\Repository\PokemonRepository;
use App\Repository\TypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pokemon', name: 'api_pokemon_')]
class PokemonController extends AbstractController
{
    // 1. GET ALL + RECHERCHE (?q=...)
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, PokemonRepository $repo): JsonResponse
    {
        $search = $request->query->get('q');

        if ($search) {
            // Recherche par nom (insensible à la casse selon la collation BDD)
            $pokemons = $repo->createQueryBuilder('p')
                ->where('p.name LIKE :term')
                ->setParameter('term', '%' . $search . '%')
                ->getQuery()
                ->getResult();
        } else {
            $pokemons = $repo->findAll();
        }

        $tab = array_map(fn(Pokemon $p) => $this->serializePokemon($p), $pokemons);

        return $this->json($tab);
    }

    // 2. GET ONE (/{id})
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, PokemonRepository $repo): JsonResponse
    {
        $pokemon = $repo->find($id);

        if (!$pokemon) {
            return $this->json(['message' => 'Pokémon introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializePokemon($pokemon));
    }

    // 3. CREATE (POST /)
    #[Route('/', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        TypeRepository $typeRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $pokemon = new Pokemon();
        $pokemon = new Pokemon();
        $pokemon->setHp(100);
        $pokemon->setCp(10);
        $pokemon->setLevel(1);
        $pokemon->setPicture('https://assets.pokemon.com/assets/cms2/img/pokedex/full/001.png');

        $this->hydratePokemon($pokemon, $data, $typeRepo);

        $em->persist($pokemon);
        $em->flush();

        // Ton front attend l'objet créé avec son nouvel ID
        return $this->json($this->serializePokemon($pokemon), Response::HTTP_CREATED);
    }

    // 4. UPDATE (PUT /{id})
    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        PokemonRepository $repo,
        TypeRepository $typeRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $pokemon = $repo->find($id);

        if (!$pokemon) {
            return $this->json(['message' => 'Pokémon introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $this->hydratePokemon($pokemon, $data, $typeRepo);
        $em->flush();

        // Ton front attend l'objet mis à jour en retour
        return $this->json($this->serializePokemon($pokemon));
    }

    // 5. DELETE (DELETE /{id})
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        PokemonRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $pokemon = $repo->find($id);

        if (!$pokemon) {
            return $this->json(['message' => 'Pokémon introuvable'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($pokemon);
        $em->flush();

        // Ton front fait `return response.json()`, donc on renvoie un objet JSON vide `{}` avec un code 200
        return $this->json(new \stdClass(), Response::HTTP_OK);
    }

    // --- MÉTHODES UTILITAIRES PRIVÉES ---

    private function hydratePokemon(Pokemon $pokemon, array $data, TypeRepository $typeRepo): void
    {
        if (isset($data['name'])) $pokemon->setName($data['name']);
        if (isset($data['hp'])) $pokemon->setHp((int) $data['hp']);
        if (isset($data['cp'])) $pokemon->setCp((int) $data['cp']);
        if (isset($data['picture'])) $pokemon->setPicture($data['picture']);
        if (isset($data['level'])) $pokemon->setLevel((int) $data['level']);

        // Gestion des types (ex: ["Feu", "Vol"])
        if (isset($data['types']) && is_array($data['types'])) {
            // On vide les anciens types
            foreach ($pokemon->getTypes() as $existingType) {
                $pokemon->removeType($existingType);
            }
            // On associe les nouveaux types trouvés en BDD par leur label
            foreach ($data['types'] as $typeLabel) {
                $typeEntity = $typeRepo->findOneBy(['label' => $typeLabel]);
                if ($typeEntity) {
                    $pokemon->addType($typeEntity);
                }
            }
        }
    }

    private function serializePokemon(Pokemon $pokemon): array
    {
        $types = [];
        foreach ($pokemon->getTypes() as $type) {
            $types[] = $type->getLabel();
        }

        return [
            'id' => $pokemon->getId(),
            'name' => $pokemon->getName(),
            'hp' => $pokemon->getHp(),
            'cp' => $pokemon->getCp(),
            'picture' => $pokemon->getPicture(),
            'level' => $pokemon->getLevel(),
            'types' => $types,
        ];
    }
}
