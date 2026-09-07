'use client';

import { FormEvent, useRef, useState } from 'react';
import { trackAnalyticsEvent } from './analytics';

type FeedbackValue = 'helpful' | 'not_helpful';
type FeedbackState = 'sending' | 'saved' | 'error';

type Message = {
  role: 'user' | 'assistant';
  content: string;
  sources?: { title: string; type: string }[];
  pending?: boolean;
  interaction?: string;
  feedback?: FeedbackValue;
  feedbackState?: FeedbackState;
};

function ThinkingAnimation() {
  return (
    <img
      src="/younz-loading.svg"
      className="ai-thinking-animation"
      alt=""
      aria-hidden="true"
    />
  );
}

const suggestions = [
  'Berapa harga print A4?',
  'Jam buka dan alamat Younz?',
  'Apa saja layanan yang tersedia?',
];

export default function AiChat() {
  const [messages, setMessages] = useState<Message[]>([]);
  const [consent, setConsent] = useState(false);
  const [pending, setPending] = useState(false);
  const [input, setInput] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);

  function scroll() {
    requestAnimationFrame(() => {
      if (scrollRef.current) {
        scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
      }
    });
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const question = input.trim();
    if (!question || pending || !consent) return;

    const history = messages
      .filter((message) => !message.pending)
      .slice(-12)
      .map(({ role, content }) => ({ role, content }));

    setMessages((current) => [
      ...current,
      { role: 'user', content: question },
      { role: 'assistant', content: '', pending: true },
    ]);
    setInput('');
    setPending(true);
    scroll();
    void trackAnalyticsEvent('ai_question_sent', 'landing_ai');

    try {
      const response = await fetch('/backend/v1/ai/stream', {
        method: 'POST',
        headers: { Accept: 'text/event-stream', 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: question, history, ai_consent: '1' }),
      });

      if (!response.ok || !response.body) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || 'Younz AI belum dapat menjawab.');
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let answer = '';
      let sources: Message['sources'] = [];
      let interaction = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue;
          const raw = line.slice(6);
          if (raw === '[DONE]') continue;

          try {
            const data = JSON.parse(raw);
            if (data.type === 'text_delta' && data.delta) {
              answer += data.delta;
              setMessages((current) => [
                ...current.slice(0, -1),
                { role: 'assistant', content: answer, pending: false, sources, interaction },
              ]);
              scroll();
            } else if (data.type === 'sources') {
              sources = data.sources || [];
            } else if (data.type === 'interaction') {
              interaction = data.interaction_token || '';
            } else if (data.type === 'error') {
              throw new Error(data.message);
            }
          } catch (error) {
            if (error instanceof SyntaxError) continue;
            throw error;
          }
        }
      }

      if (!answer) throw new Error('Jawaban kosong. Silakan coba lagi.');

      setMessages((current) => [
        ...current.slice(0, -1),
        { role: 'assistant', content: answer, sources, interaction },
      ]);
    } catch (error) {
      setMessages((current) => [
        ...current.slice(0, -1),
        {
          role: 'assistant',
          content: error instanceof Error ? error.message : 'Layanan sedang tidak tersedia.',
        },
      ]);
    } finally {
      setPending(false);
      scroll();
    }
  }

  function updateFeedback(
    interaction: string,
    value: FeedbackValue,
    state: FeedbackState,
  ) {
    setMessages((current) =>
      current.map((message) =>
        message.interaction === interaction
          ? { ...message, feedback: value, feedbackState: state }
          : message,
      ),
    );
  }

  async function feedback(index: number, value: FeedbackValue) {
    const message = messages[index];
    const interaction = message?.interaction;

    if (
      !interaction ||
      message.feedbackState === 'sending' ||
      message.feedbackState === 'saved'
    ) {
      return;
    }

    updateFeedback(interaction, value, 'sending');

    try {
      const response = await fetch('/backend/v1/ai/feedback', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ interaction_token: interaction, feedback: value }),
      });

      if (!response.ok) {
        throw new Error('Feedback belum dapat disimpan.');
      }

      updateFeedback(interaction, value, 'saved');
    } catch {
      updateFeedback(interaction, value, 'error');
    }
  }

  return (
    <div className="next-ai-chat">
      <header>
        <span className="ai-chat-mark"><img src="/brand/younz-wordmark-v1.svg" alt="" aria-hidden="true" /></span>
        <div>
          <strong>Younz AI</strong>
          <small>Siap membantu</small>
        </div>
        <button type="button" onClick={() => setMessages([])}>
          Hapus riwayat
        </button>
      </header>

      <div className="ai-messages" ref={scrollRef} aria-live="polite">
        {messages.length === 0 ? (
          <div className="ai-empty">
            <span><img src="/brand/younz-wordmark-v1.svg" alt="" aria-hidden="true" /></span>
            <p>
              Bahas apa saja selain coding. Informasi khusus Younz berasal dari data
              layanan terverifikasi.
            </p>
            <div>
              {suggestions.map((item) => (
                <button key={item} type="button" onClick={() => setInput(item)}>
                  {item}
                </button>
              ))}
            </div>
          </div>
        ) : (
          messages.map((message, index) => {
            const feedbackDisabled =
              message.feedbackState === 'sending' || message.feedbackState === 'saved';
            const feedbackText =
              message.feedbackState === 'sending'
                ? 'Menyimpan...'
                : message.feedbackState === 'saved'
                  ? 'Terima kasih atas masukannya.'
                  : message.feedbackState === 'error'
                    ? 'Gagal tersimpan. Coba lagi.'
                    : 'Bermanfaat?';

            return (
              <div
                className={`ai-message ${message.role}`}
                key={`${index}-${message.role}`}
              >
                {message.role === 'assistant' && (
                  <span
                    className={`message-avatar${message.pending ? ' is-thinking' : ''}`}
                  >
                    {message.pending ? <ThinkingAnimation /> : <img src="/brand/younz-wordmark-v1.svg" alt="" aria-hidden="true" />}
                  </span>
                )}
                <div>
                  <p>
                    {message.pending
                      ? 'Younz AI sedang menyiapkan jawaban...'
                      : message.content}
                  </p>

                  {message.sources && message.sources.length > 0 && (
                    <div className="ai-sources">
                      {message.sources.map((source) => (
                        <span key={`${source.type}-${source.title}`}>{source.title}</span>
                      ))}
                    </div>
                  )}

                  {message.interaction && (
                    <div className="ai-feedback">
                      <small
                        className={`ai-feedback-status ${
                          message.feedbackState
                            ? `is-${message.feedbackState}`
                            : ''
                        }`}
                        role="status"
                      >
                        {feedbackText}
                      </small>
                      <button
                        type="button"
                        className={
                          message.feedback === 'helpful' ? 'is-selected' : ''
                        }
                        disabled={feedbackDisabled}
                        aria-label="Jawaban ini bermanfaat"
                        aria-pressed={message.feedback === 'helpful'}
                        onClick={() => feedback(index, 'helpful')}
                      >
                        👍
                      </button>
                      <button
                        type="button"
                        className={
                          message.feedback === 'not_helpful'
                            ? 'is-selected is-negative'
                            : ''
                        }
                        disabled={feedbackDisabled}
                        aria-label="Jawaban ini tidak bermanfaat"
                        aria-pressed={message.feedback === 'not_helpful'}
                        onClick={() => feedback(index, 'not_helpful')}
                      >
                        👎
                      </button>
                    </div>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>

      <form onSubmit={submit}>
        <div className="ai-input-row">
          <input
            value={input}
            onChange={(event) => setInput(event.target.value)}
            required
            maxLength={3000}
            placeholder="Tulis pertanyaan Anda..."
          />
          <button disabled={pending || !consent} aria-label="Kirim pertanyaan">
            →
          </button>
        </div>
        <label className="ai-consent">
          <input
            type="checkbox"
            checked={consent}
            onChange={(event) => setConsent(event.target.checked)}
            required
          />
          <span>
            Saya memahami pertanyaan dapat diproses penyedia AI pihak ketiga. Jangan
            masukkan data pribadi atau rahasia.
          </span>
        </label>
      </form>
    </div>
  );
}
