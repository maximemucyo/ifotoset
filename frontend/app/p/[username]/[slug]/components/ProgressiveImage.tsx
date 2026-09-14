import React, { useState } from 'react';
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
  const fullUrl = photo.variants?.[targetVariant] || photo.cdn_url;
  const [isLoaded, setIsLoaded] = useState(false);

  return (
    <div
      className={`relative overflow-hidden w-full h-full bg-secondary/10 select-none ${className}`}
      style={style}
      onClick={onClick}
    >
      {/* Blurhash Placeholder */}
      {blurhashUrl && (
        <img
          src={blurhashUrl}
          alt=""
          aria-hidden="true"
          className={`absolute inset-0 w-full h-full object-cover transition-opacity duration-500 pointer-events-none ${
            isLoaded ? 'opacity-0' : 'opacity-100'
          }`}
        />
      )}

      {/* Main Photo */}
      <img
        src={fullUrl}
        alt={alt}
        className={`relative z-[1] w-full h-full object-cover transition-opacity duration-500 ${
          isLoaded ? 'opacity-100' : 'opacity-0'
        }`}
        loading="lazy"
        onLoad={() => setIsLoaded(true)}
        ref={(img) => {
          if (img && img.complete && img.naturalWidth > 0 && !isLoaded) {
            setIsLoaded(true);
          }
        }}
      />
    </div>
  );
};
