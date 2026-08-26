import { Button } from "@/components/ui/button";
import { Head, Link } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";

interface Props {
  title: string;
  message: string;
  form_url: string;
}

export default function FormsThanks({ title, message, form_url }: Props) {
  return (
    <>
      <Head title="Response recorded" />
      <main className="bg-muted/30 flex min-h-screen items-center justify-center px-4 py-12">
        <section className="border-border/70 bg-card flex w-full max-w-xl flex-col items-center gap-4 rounded-2xl border p-8 text-center shadow-sm sm:p-12">
          <CheckCircle2
            className="size-14 text-emerald-600"
            aria-hidden="true"
          />
          <p className="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">
            Response recorded
          </p>
          <h1 className="text-3xl font-semibold tracking-[-0.04em]">
            Thank you
          </h1>
          <p className="text-muted-foreground max-w-md text-sm leading-6">
            {message}
          </p>
          <Button asChild variant="outline" className="mt-3">
            <Link href={form_url}>Open form again</Link>
          </Button>
          <p className="text-muted-foreground text-xs">{title}</p>
        </section>
      </main>
    </>
  );
}
