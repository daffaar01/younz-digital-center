import Image from 'next/image';
import { Banner } from '@/components/ui/banner';

function RainbowBannerDemo() {
  return (
    <div className="relative w-full">
      <Banner
        message="🎉 New features coming soon!"
        height="2rem"
        variant="rainbow"
        className="mb-4"
      />

      <div className="relative aspect-[16/9] w-full overflow-hidden rounded-lg">
        <Image
          src="https://images.unsplash.com/photo-1559028012-481c04fa702d?auto=format&fit=crop&w=1600&q=80"
          alt="Application workspace"
          fill
          className="object-cover"
          sizes="(max-width: 768px) 100vw, 1200px"
          priority
        />
      </div>
    </div>
  );
}

function BannerDemo() {
  return (
    <div className="relative w-full">
      <Banner
        message="🎉 New features coming soon!"
        height="2rem"
        className="mb-4"
      />

      <div className="relative aspect-[16/9] w-full overflow-hidden rounded-lg">
        <Image
          src="https://images.unsplash.com/photo-1559028012-481c04fa702d?auto=format&fit=crop&w=1600&q=80"
          alt="Application workspace"
          fill
          className="object-cover"
          sizes="(max-width: 768px) 100vw, 1200px"
          priority
        />
      </div>
    </div>
  );
}

export { RainbowBannerDemo, BannerDemo };
