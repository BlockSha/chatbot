<?php

namespace App\Controller;

use LLPhant\Chat\MistralChat;
use LLPhant\MistralConfig;
use LLPhant\Chat\Message;
use LLPhant\Chat\Enums\ChatRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function index(Request $request): JsonResponse
    {
        // 1. Récupérer le message envoyé par le front (ou Postman)
        $data = json_decode($request->getContent(), true);
        $userContent = $data['message'] ?? null;

        if (!$userContent) {
            return $this->json(['error' => 'Le message est vide 😔'], 400);
        }

        // 2. Configuration de Mistral
        // On récupère la clé depuis le fichier .env
        $config = new MistralConfig();
        $config->apiKey = $_ENV['MISTRAL_API_KEY'];
        // Tu peux choisir le modèle : 'mistral-tiny', 'mistral-small', 'mistral-medium', 'mistral-large-latest'
        $config->model = 'mistral-small-latest';

        $chat = new MistralChat($config);

        // 3. Création du message système (pour donner une personnalité au bot)
        $systemMessage = new Message();
        $systemMessage->role = ChatRole::System;
        $systemMessage->content = "Tu es un assistant sympathique et expert en développement Web.";

        // 4. Création du message utilisateur
        $userMessage = new Message();
        $userMessage->role = ChatRole::User;
        $userMessage->content = $userContent;

        // 5. Envoi à Mistral et récupération de la réponse
        // On envoie un tableau de messages (l'historique)
        try {
            $response = $chat->generateChat([$systemMessage, $userMessage]);

            return $this->json([
                'response' => $response->getContent()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
