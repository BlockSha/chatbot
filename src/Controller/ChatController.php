<?php

namespace App\Controller;

use LLPhant\Chat\MistralAIChat;
use LLPhant\MistralAIConfig;
use LLPhant\Embeddings\EmbeddingGenerator\Mistral\MistralEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\Query\SemanticSearch\QuestionAnswering;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ChatController extends AbstractController
{
    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function index(
        Request $request,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['message'] ?? null;

        if (!$question) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        try {
            $apiKey = $_ENV['MISTRAL_API_KEY'] ?? null;
            if (!$apiKey) {
                throw new \Exception('Clé API Mistral manquante.');
            }

            // 1. CONFIGURATION
            $config = new MistralAIConfig();
            $config->apiKey = $apiKey;

            // 2. EMBEDDINGS
            // On enlève le setClient qui n'existe pas
            $embeddingGenerator = new MistralEmbeddingGenerator($config);

            // 3. VECTOR STORE
            $vectorPath = $projectDir . '/var/vector_store.json';
            if (!file_exists($vectorPath)) {
                throw new \Exception("Fichier vector_store.json introuvable.");
            }
            $vectorStore = new FileSystemVectorStore($vectorPath);

            // 4. CHAT
            // On utilise bien MistralAIChat
            $chat = new MistralAIChat($config);

            // 5. QUESTION ANSWERING
            $qa = new QuestionAnswering($vectorStore, $embeddingGenerator, $chat);

            $qa->systemMessageTemplate = "Tu es un expert. Utilise ce contexte pour répondre : {context}. Question : {question}";

            $answer = $qa->answerQuestion($question);

            return $this->json([
                'response' => $answer
            ]);

        } catch (\Throwable $e) { // Throwable attrape TOUT (Erreurs et Exceptions)
            return $this->json([
                'error' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }
}
