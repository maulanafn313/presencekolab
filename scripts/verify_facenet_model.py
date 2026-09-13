"""Read-only model verification without a database or user photographs."""
import hashlib
import json
import sys
from pathlib import Path
import torch
from facenet_pytorch import InceptionResnetV1

model_path = Path(sys.argv[1])
weights = torch.load(model_path, map_location='cpu', weights_only=True)
weights.pop('logits.weight', None)
weights.pop('logits.bias', None)
model = InceptionResnetV1(pretrained=None).eval()
model.load_state_dict(weights, strict=True)
torch.manual_seed(17)
sample = torch.rand(1, 3, 160, 160)
with torch.inference_mode():
    first = model(sample)
    second = model(sample)
assert first.shape == (1, 512)
assert torch.isfinite(first).all()
assert torch.allclose(first, second)
assert abs(float(torch.linalg.vector_norm(first)) - 1.0) < 1e-5
print(json.dumps({'ok': True, 'dimensions': 512, 'deterministic': True,
                  'sha256': hashlib.sha256(model_path.read_bytes()).hexdigest()}))
