import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const [scenesPath, audioRoot] = process.argv.slice(2);

if (!scenesPath || !audioRoot) {
  throw new Error('Usage: node generate_narration.mjs SCENES_JSON AUDIO_ROOT');
}

const apiKey = process.env.ELEVENLABS_API_KEY || '';
const voiceId = process.env.ELEVENLABS_VOICE_ID || '';
const modelId = process.env.ELEVENLABS_MODEL_ID || 'eleven_multilingual_v2';
const outputFormat = process.env.ELEVENLABS_OUTPUT_FORMAT || 'mp3_44100_192';

if (!apiKey || !voiceId) {
  throw new Error('ELEVENLABS_API_KEY and ELEVENLABS_VOICE_ID are required.');
}

const settings = {
  stability: Number(process.env.ELEVENLABS_STABILITY || '0.48'),
  similarity_boost: Number(process.env.ELEVENLABS_SIMILARITY_BOOST || '0.78'),
  style: Number(process.env.ELEVENLABS_STYLE || '0.18'),
  use_speaker_boost:
    (process.env.ELEVENLABS_SPEAKER_BOOST || 'true').toLowerCase() === 'true',
  speed: Number(process.env.ELEVENLABS_SPEED || '0.96'),
};

const scenes = JSON.parse(fs.readFileSync(scenesPath, 'utf8'));
fs.mkdirSync(audioRoot, { recursive: true });

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

for (let index = 0; index < scenes.length; index += 1) {
  const scene = scenes[index];
  const number = String(index + 1).padStart(2, '0');
  const outputPath = path.join(audioRoot, `${number}-${scene.id}.mp3`);
  const endpoint =
    `https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(voiceId)}` +
    `?output_format=${encodeURIComponent(outputFormat)}`;

  console.log(`[TTS] ${number}/${String(scenes.length).padStart(2, '0')} ${scene.id}`);

  let lastError;

  for (let attempt = 1; attempt <= 4; attempt += 1) {
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          Accept: 'audio/mpeg',
          'Content-Type': 'application/json',
          'xi-api-key': apiKey,
        },
        body: JSON.stringify({
          text: scene.narration,
          model_id: modelId,
          voice_settings: settings,
        }),
      });

      if (!response.ok) {
        const body = await response.text();
        throw new Error(
          `HTTP ${response.status}: ${body.slice(0, 600)}`
        );
      }

      const audio = Buffer.from(await response.arrayBuffer());
      if (audio.length < 1000) {
        throw new Error(`Audio response too small: ${audio.length} bytes`);
      }

      fs.writeFileSync(outputPath, audio);
      lastError = null;
      break;
    } catch (error) {
      lastError = error;
      if (attempt < 4) {
        await sleep(1000 * attempt * attempt);
      }
    }
  }

  if (lastError) {
    throw lastError;
  }
}
