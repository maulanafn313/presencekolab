<?php

namespace Tests\Feature;

use App\Services\FaceEmbeddingInput;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FaceEmbeddingInputTest extends TestCase
{
    public function test_accepts_frontend_descriptor_and_point_formats(): void
    {
        foreach ([['x' => 1, 'y' => 2], ['_x' => 1, '_y' => 2]] as $point) {
            FaceEmbeddingInput::validate(json_encode(array_fill(0, 128, 0.1)), json_encode(array_fill(0, 68, $point)));
        }
        $this->addToAssertionCount(2);
    }

    public function test_rejects_bad_dimensions_types_nonfinite_and_oversized_input(): void
    {
        foreach ([null, [], '[0.1]', '{"a":1}', json_encode(array_fill(0, 128, '0.1')), '['.implode(',', array_fill(0, 128, '1e999')).']', str_repeat(' ', 16385)] as $value) {
            try {
                FaceEmbeddingInput::validate($value, null);
                $this->fail('Invalid descriptor was accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_invalid_landmarks_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FaceEmbeddingInput::validate(json_encode(array_fill(0, 128, 0.1)), '[]');
    }

    public function test_handler_rejects_invalid_embedding_without_database_change(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/legacy_security.php'), 'admin', 'save_face_embedding', 'POST', 'invalid']);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(422, $result['status']);
        $this->assertSame('original', $result['embedding']);
    }
}
