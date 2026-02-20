CREATE TABLE "documents" ("id" BIGINT NOT NULL AUTO_INCREMENT, "email" VARCHAR(255) NOT NULL DEFAULT '', "data" JSONB NULL DEFAULT NULL, "location" TEXT NULL DEFAULT NULL, "tags" TEXT NULL DEFAULT NULL, "created_at" TIMESTAMPTZ NOT NULL DEFAULT '1970-01-01 00:00:01', PRIMARY KEY ("id"));
CREATE UNIQUE INDEX "idx_documents_3885137012" ON "documents"("email");
CREATE INDEX "idx_documents_61798570" ON "documents" USING GIN ("data");
CREATE INDEX "idx_documents_2001579032" ON "documents" USING GiST ("location");
CREATE INDEX "idx_documents_3131025577" ON "documents" USING HASH ("tags");
CREATE INDEX "idx_documents_1624827106" ON "documents" USING BRIN ("created_at");
CREATE INDEX "idx_documents_412220356" ON "documents"("email", "created_at");
