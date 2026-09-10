type TranslateOptions = Record<string, unknown>;

const i18n = {
  t: (key: string, _options?: TranslateOptions): string => key,
};

export default i18n;
