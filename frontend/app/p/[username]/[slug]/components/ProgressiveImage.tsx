import React, { useState, useEffect } from 'react';
import { PhotoItem } from '@/lib/queries/galleries';
import { blurHashToDataUrl } from '@/lib/blurhash';

interface ProgressiveImageProps {
  photo: PhotoItem;
  alt: string;
  className?: string;
  style?: React.CSSProperties;
  onClick?: () => void;
  targetVariant?: 'sm' | 'md' | 'lg' | 'xl';
}

export const ProgressiveImage: React.FC<ProgressiveImageProps> = ({
  photo,
  alt,
  className = '',
  style = {},
  onClick,
  targetVariant = 'md',
}) => {
  const blurhashUrl = blurHashToDataUrl(photo.blurhash || '', 32, 32);
  const xsUrl = photo.variants?.xs;
  const fullUrl = photo.variants?.[targetVariant] || photo.cdn_url;

  const [src, setSrc] = useState<string | null>(xsUrl || fullUrl || null);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    let active = true;

    const loadImage = async () => {
      // Step 1: Start with BlurHash (if available) or the xsUrl
      if (blurhashUrl) {
        setSrc(blurhashUrl);
      } else if (xsUrl) {
        setSrc(xsUrl);
      }

      // Step 2: Load xs variant first as a step up
      if (xsUrl) {
        const xsImg = new Image();
        xsImg.src = xsUrl;
        xsImg.onload = () => {
          if (active && !isLoaded) {
            setSrc(xsUrl);
          }
        };
      }

      // Step 3: Load the full variant, decode, then render
      const fullImg = new Image();
      fullImg.src = fullUrl;
      try {
        await fullImg.decode();
        if (active) {
          setSrc(fullUrl);
          setIsLoaded(true);
        }
      } catch (err) {
        fullImg.onload = () => {
          if (active) {
            setSrc(fullUrl);
            setIsLoaded(true);
          }
        };
      }
    };

    loadImage();

    return () => {
      active = false;
    };
  }, [fullUrl, xsUrl, blurhashUrl, isLoaded]);

  return (
    <div
      className={`relative overflow-hidden w-full h-full bg-secondary/10 select-none ${className}`}
      style={style}
      onClick={onClick}
    >
      {src && (
        <img
          src={src}
          alt={alt}
          className={`w-full h-full object-cover transition-all duration-500 ease-in-out ${
            isLoaded ? 'blur-0 scale-100' : 'blur-lg scale-105'
          }`}
          loading="lazy"
        />
      )}
    </div>
  );
};
