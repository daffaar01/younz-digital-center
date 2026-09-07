export type DigitalProductVariant = {
  id: number;
  label: string;
  price: number | null;
  price_label: string;
  stock: number | null;
  stock_label: string;
  sort_order: number;
};

export type DigitalProduct = {
  id: number;
  name: string;
  category: 'Komunitas' | 'Streaming' | 'AI Assistant' | 'AI Kreatif';
  mark: string | null;
  image_url: string;
  stock: number | null;
  stock_label: string;
  price: number | null;
  price_label: string;
  description: string;
  sort_order: number;
  variants: DigitalProductVariant[];
};
