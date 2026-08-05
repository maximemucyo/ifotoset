const charMap = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!#$%&()*+,-./:;<=>?@[]^_{|}~";
const charMapDict: Record<string, number> = {};
for (let i = 0; i < charMap.length; i++) {
  charMapDict[charMap[i]] = i;
}

function decode83(str: string): number {
  let value = 0;
  for (let i = 0; i < str.length; i++) {
    const c = str[i];
    const digit = charMapDict[c] ?? 0;
    value = value * 83 + digit;
  }
  return value;
}

function sRGBToLinear(value: number): number {
  const v = value / 255;
  if (v <= 0.04045) {
    return v / 12.92;
  }
  return Math.pow((v + 0.055) / 1.055, 2.4);
}

function linearTosRGB(value: number): number {
  const v = Math.max(0, Math.min(1, value));
  if (v <= 0.0031308) {
    return Math.round(v * 12.92 * 255);
  }
  return Math.round((1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255);
}

function signPow(val: number, exp: number): number {
  return Math.sign(val) * Math.pow(Math.abs(val), exp);
}

export function decodeBlurHash(
  blurhash: string,
  width: number,
  height: number,
  punch = 1
): Uint8ClampedArray | null {
  if (!blurhash || blurhash.length < 6) return null;

  const numComponentsVal = decode83(blurhash[0]);
  const numX = (numComponentsVal % 9) + 1;
  const numY = Math.floor(numComponentsVal / 9) + 1;

  const quantisedValue = decode83(blurhash[1]);
  const maxVal = (quantisedValue + 1) / 166;

  const colors: number[][] = [];
  for (let i = 0; i < numX * numY; i++) {
    if (i === 0) {
      const value = decode83(blurhash.substring(2, 6));
      colors.push([
        sRGBToLinear(value >> 16),
        sRGBToLinear((value >> 8) & 255),
        sRGBToLinear(value & 255)
      ]);
    } else {
      const value = decode83(blurhash.substring(6 + (i - 1) * 2, 6 + i * 2));
      colors.push([
        signPow((Math.floor(value / (19 * 19)) - 9) / 9, 2.0) * maxVal * punch,
        signPow((Math.floor(value / 19) % 19 - 9) / 9, 2.0) * maxVal * punch,
        signPow((value % 19 - 9) / 9, 2.0) * maxVal * punch
      ]);
    }
  }

  const bytes = new Uint8ClampedArray(width * height * 4);

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      let r = 0;
      let g = 0;
      let b = 0;

      for (let j = 0; j < numY; j++) {
        for (let i = 0; i < numX; i++) {
          const basis =
            Math.cos((Math.PI * x * i) / width) *
            Math.cos((Math.PI * y * j) / height);
          const color = colors[i + j * numX];
          if (color) {
            r += color[0] * basis;
            g += color[1] * basis;
            b += color[2] * basis;
          }
        }
      }

      const index = 4 * (x + y * width);
      bytes[index] = linearTosRGB(r);
      bytes[index + 1] = linearTosRGB(g);
      bytes[index + 2] = linearTosRGB(b);
      bytes[index + 3] = 255;
    }
  }

  return bytes;
}

const blurHashCache = new Map<string, string>();
const MAX_CACHE_SIZE = 500;

export function blurHashToDataUrl(blurhash: string, width = 32, height = 32): string | null {
  if (!blurhash) return null;
  
  if (blurHashCache.has(blurhash)) {
    const val = blurHashCache.get(blurhash)!;
    blurHashCache.delete(blurhash);
    blurHashCache.set(blurhash, val);
    return val;
  }

  try {
    if (typeof document === 'undefined') return null;

    const pixels = decodeBlurHash(blurhash, width, height);
    if (!pixels) return null;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;

    const imageData = ctx.createImageData(width, height);
    imageData.data.set(pixels);
    ctx.putImageData(imageData, 0, 0);

    const dataUrl = canvas.toDataURL();

    if (blurHashCache.size >= MAX_CACHE_SIZE) {
      const oldestKey = blurHashCache.keys().next().value;
      if (oldestKey) {
        blurHashCache.delete(oldestKey);
      }
    }
    blurHashCache.set(blurhash, dataUrl);
    return dataUrl;
  } catch (err) {
    console.error("Failed to decode blurhash", err);
    return null;
  }
}
