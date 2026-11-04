<?php

namespace App\Services\Ai\Contracts;

/**
 * Kontrak seragam seluruh driver AI (Master §8). Browser tidak pernah
 * berbicara langsung dengan AI Server: seluruh panggilan melalui backend.
 */
interface AiDriver
{
    /** Identitas driver: mock | ollama | openai_compatible | external */
    public function name(): string;

    /** true bila keluaran driver adalah SIMULASI (MockDriver). */
    public function isSimulation(): bool;

    /** @return array{status:string,latency_ms:?int,runtime:?string,runtime_version:?string,error:?string} */
    public function checkHealth(): array;

    /** @return array<int,array{name:string,size:?string,quantization:?string,context_length:?int,status:string}> */
    public function listModels(): array;

    /** @return array<string,mixed> */
    public function getModelInfo(string $model): array;

    /**
     * Percakapan bebas (konsol analis).
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @return array{content:string,model:string,is_simulation:bool}
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Analisis terstruktur. Mengembalikan TEKS MENTAH keluaran model;
     * parsing dan validasi skema dilakukan backend (AiManager).
     *
     * @param  array{module:string,messages:array,payload:array} $request
     */
    public function analyze(array $request): string;

    /**
     * Embedding untuk Semantic Similarity.
     *
     * @param  string[]  $texts
     * @return array<int,array<int,float>>
     */
    public function embed(array $texts): array;

    /** @return array{ok:bool,message:string,details:array} */
    public function validateConnection(): array;

    public function modelName(): ?string;
}
