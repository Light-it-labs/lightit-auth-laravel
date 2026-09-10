export type GoogleLoginResponse = {
  accessToken: string;
  tokenType: "Bearer";
  expiresIn: number;
};

export type GoogleLoginPayload = {
  idToken: string;
};
