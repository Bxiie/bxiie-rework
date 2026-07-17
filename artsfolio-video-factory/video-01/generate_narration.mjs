import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const [scenesPath, audioRoot] = process.argv.slice(2);
if (!scenesPath || !audioRoot) throw new Error('Missing scenes path or audio root.');

const apiKey = process.env.ELEVENLABS_API_KEY || '';
const voiceId = process.env.ELEVENLABS_VOICE_ID || '';
const modelId = process.env.ELEVENLABS_MODEL_ID || 'eleven_multilingual_v2';
const outputFormat = process.env.ELEVENLABS_OUTPUT_FORMAT || 'mp3_44100_192';
if (!apiKey || !voiceId) throw new Error('ElevenLabs credentials are missing.');

const scenes = JSON.parse(fs.readFileSync(scenesPath, 'utf8'));
fs.mkdirSync(audioRoot, { recursive: true });

const voiceSettings = {
  stability: Number(process.env.ELEVENLABS_STABILITY || '0.52'),
  similarity_boost: Number(process.env.ELEVENLABS_SIMILARITY_BOOST || '0.78'),
  style: Number(process.env.ELEVENLABS_STYLE || '0.12'),
  use_speaker_boost: (process.env.ELEVENLABS_SPEAKER_BOOST || 'true') === 'true',
  speed: Number(process.env.ELEVENLABS_SPEED || '0.88'),
};

const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

let ordinal = 0;
for (const scene of scenes) {
  for (const cue of scene.cues) {
    ordinal += 1;
    const number = String(ordinal).padStart(3, '0');
    const filename = `${number}-${scene.id}-${cue.id}.mp3`;
    const destination = path.join(audioRoot, filename);
    const endpoint =
      `https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(voiceId)}` +
      `?output_format=${encodeURIComponent(outputFormat)}`;

    console.log(`[TTS] ${number} ${scene.id}/${cue.id}`);

    let lastError = null;
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
            text: cue.narration,
            model_id: modelId,
            voice_settings: voiceSettings,
          }),
        });

        if (!response.ok) {
          throw new Error(`ElevenLabs HTTP ${response.status}: ${(await response.text()).slice(0, 500)}`);
        }

        const data = Buffer.from(await response.arrayBuffer());
        if (data.length < 1000) throw new Error(`Audio response too small: ${data.length}`);
        fs.writeFileSync(destination, data);
        lastError = null;
        break;
      } catch (error) {
        lastError = error;
        if (attempt < 4) await wait(attempt * attempt * 1000);
      }
    }
    if (lastError) throw lastError;
  }
}
