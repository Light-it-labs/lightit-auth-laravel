import { PasskeysSection } from "./-components/passkeys-section";
import { TwoFactorSection } from "./-components/two-factor-section";

export default function SecurityPage() {
  return (
    <div>
      <h1>Security</h1>
      <TwoFactorSection />
      <PasskeysSection />
    </div>
  );
}
