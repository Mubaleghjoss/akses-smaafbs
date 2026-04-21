<?php

namespace Tests\Feature\Concerns;

trait BootstrapsAdminFeatureTables
{
    use BootstrapsAdminPanel;
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUksAndPrestasiTables;
    use BootstrapsUserAndPermissionTables;

    protected function bootstrapAdminFeatureTables(): void
    {
        $this->bootstrapAdminPanel();
        $this->bootstrapUserAndPermissionTables();
        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUksAndPrestasiTables();
    }
}
