<?php

namespace App\Command;

use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
// VOICI LA CORRECTION : On ajoute \Mistral avant le nom de la classe
use LLPhant\Embeddings\EmbeddingGenerator\Mistral\MistralEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\FileSystem\FileSystemVectorStore;
use LLPhant\MistralAIConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vectors',
    description: 'Transforme les documents en vecteurs (Embeddings)',
)]
class GenerateVectorCommand extends Command
{
    public function __construct(private string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Génération des vecteurs en cours...');

        // 1. CONFIGURATION
        $apiKey = $_ENV['MISTRAL_API_KEY'] ?? null;
        if (!$apiKey) {
            $io->error('Clé API Mistral manquante dans .env.local');
            return Command::FAILURE;
        }

        $config = new MistralAIConfig();
        $config->apiKey = $apiKey;

        // 2. LECTURE DES DONNÉES
        $sourceDir = $this->projectDir . '/assets/data';
        if (!is_dir($sourceDir)) {
            $io->error("Le dossier $sourceDir n'existe pas.");
            return Command::FAILURE;
        }

        // FileDataReader détecte automatiquement les .docx grâce à phpword que tu as installé
        $reader = new FileDataReader($sourceDir);
        $documents = $reader->getDocuments();

        if (empty($documents)) {
            $io->warning('Aucun document trouvé dans assets/data.');
            return Command::SUCCESS;
        }
        $io->text(count($documents) . ' document(s) trouvé(s).');

        // 3. DÉCOUPAGE
        $splitDocuments = DocumentSplitter::splitDocuments($documents, 800);

        // 4. GÉNÉRATION DES VECTEURS
        $embeddingGenerator = new MistralEmbeddingGenerator($config);
        $embeddedDocuments = $embeddingGenerator->embedDocuments($splitDocuments);

        // 5. STOCKAGE
        $vectorStorePath = $this->projectDir . '/var/vector_store.json';
        $vectorStore = new FileSystemVectorStore($vectorStorePath);
        $vectorStore->addDocuments($embeddedDocuments);

        $io->success('✅ Vecteurs générés avec succès dans var/vector_store.json !');

        return Command::SUCCESS;
    }
}
