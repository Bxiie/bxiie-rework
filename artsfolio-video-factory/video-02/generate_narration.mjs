import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const [scenesPath, audioRoot] = process.argv.slice(2);
const apiKey = process.env.ELEVENLABS_API_KEY || '';
const voiceId = process.env.ELEVENLABS_VOICE_ID || '';
const modelId = process.env.ELEVENLABS_MODEL_ID || 'eleven_multilingual_v2';
const outputFormat = process.env.ELEVENLABS_OUTPUT_FORMAT || 'mp3_44100_192';
if (!apiKey || !voiceId) throw new Error('ElevenLabs credentials are missing.');
const scenes = JSON.parse(fs.readFileSync(scenesPath, 'utf8'));
fs.mkdirSync(audioRoot, { recursive: true });
const settings = {
  stability: Number(process.env.ELEVENLABS_STABILITY || '0.52'),
  similarity_boost: Number(process.env.ELEVENLABS_SIMILARITY_BOOST || '0.78'),
  style: Number(process.env.ELEVENLABS_STYLE || '0.12'),
  use_speaker_boost: (process.env.ELEVENLABS_SPEAKER_BOOST || 'true') === 'true',
  speed: Number(process.env.ELEVENLABS_SPEED || '0.90'),
};
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
let ordinal=0;
for (const scene of scenes) {
  for (const cue of scene.cues) {
    ordinal += 1;
    const key=`${String(ordinal).padStart(3,'0')}-${scene.id}-${cue.id}`;
    const endpoint=`https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(voiceId)}?output_format=${encodeURIComponent(outputFormat)}`;
    let lastError;
    for (let attempt=1;attempt<=4;attempt+=1) {
      try {
        const response=await fetch(endpoint,{method:'POST',headers:{Accept:'audio/mpeg','Content-Type':'application/json','xi-api-key':apiKey},body:JSON.stringify({text:cue.narration,model_id:modelId,voice_settings:settings})});
        if (!response.ok) throw new Error(`ElevenLabs HTTP ${response.status}: ${(await response.text()).slice(0,500)}`);
        const data=Buffer.from(await response.arrayBuffer());
        if (data.length<1000) throw new Error(`Audio response too small: ${data.length}`);
        fs.writeFileSync(path.join(audioRoot,`${key}.mp3`),data);
        lastError=null; break;
      } catch (error) { lastError=error; if(attempt<4) await wait(attempt*attempt*1000); }
    }
    if(lastError) throw lastError;
    console.log(`[TTS] ${key}`);
  }
}
